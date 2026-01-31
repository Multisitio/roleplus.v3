<?php
/**
 */
class _preview extends _cut
{
    # 1
	public static function url($content)
	{
		$url = self::getURL($content);
		$html = self::getHtml($url);
		if ( ! $html) {
			return [];
		}

		$array['titles'] = self::getTitles($html);
		$array['descriptions'] = self::getDescriptions($html);
		$array['images'] = self::getImages($html, $url);
		#_var::die($array);
		return $array;
	}

    # 1.1a
	public static function getURL($url)
	{
		# YouTube
        if (stristr($url, 'youtu')) {
			# https://www.youtube.com/live/RkYHkv8Xoc8?feature=share
			if (stristr($url, 'youtu.be') || stristr($url, '/live/')) {
				if (strstr($url, '?')) {
					$url = explode('?', $url, 2)[0];
				}
				preg_match('/youtu\.be\/([\d\w]+)/', $url, $mat);
				$id = $mat[1];
				$url = "https://www.youtube.com/watch?v=$id";
			}
			return $url;
		}

		# Others
		$url = explode('http', $url, 2)[1];
		$url = preg_split('/\s/', "http$url")[0];
		return $url;
	}

    # 1.1b
	public static function getHtml($url)
	{
		# YouTube
        if (stristr($url, 'youtu')) {
			$html = _link::curl_get_file_contents("https://www.youtube.com/oembed?url=$url&format=json");
			return json_decode($html);
		}

		# Others
		$html = _link::curl_get_file_contents($url);
		#_var::die($html, 1);
		return self::toUTF8($html);
	}

	# 1.1.1
	public static function toUTF8($html)
	{
		if ( ! mb_detect_encoding($html, "auto", true)) {  
			return utf8_encode($html);
		}
		return $html;
	}

    # 1.2
	public static function getTitles($html)
	{
		# YouTube
		if (is_object($html)) {
			return [$html->title ?? ''];
		}

		# Others
		$titles[] = parent::cut('<title>', $html, '</title>');
		$titles[] = parent::cut('<h1>', $html, '</h1>');
		$titles[] = parent::cut('<h2>', $html, '</h2>');
		$titles[] = parent::cut('<h3>', $html, '</h3>');
		$titles[] = parent::cut('<h4>', $html, '</h4>');
		$titles[] = parent::cut('<h5>', $html, '</h5>');
		$titles[] = parent::cut('<h6>', $html, '</h6>');
		return array_filter($titles, 'strlen');
	}

    # 1.3
	public static function getDescriptions($html)
	{
		#_var::die(mb_detect_encoding($html, "auto", true));

		# YouTube
		if (is_object($html)) {
			return [$html->author_name ?? ''];
		}
		
		# Others
		$metas = self::getTags($html, 'meta');
		#_var::die($metas);
		foreach ($metas as $attrs) {
			if (empty($attrs['content'])) {
				continue;
			}
			if ( ! empty($attrs['name']) && $attrs['name']=='description') {
				$descriptions[] = $attrs['content'];
			}
			if ( ! empty($attrs['property']) && $attrs['property']=='og:description') {
				$descriptions[] = $attrs['content'];
			}
		}
		$body = parent::cut('<body>', $html, '</body>');
		$body = self::nlToTag($body);
		$body = self::stripTags($body);
        $body = self::onlyOneSpace($body);
        $body = self::onlyOneNl($body);
        #$body = self::seeTheUnseen($body);
		$descriptions[] = trim($body);
		return array_filter($descriptions, 'strlen');
	}

	# 1.3.1 
    public static function getTags($html, $tag_selected)
    {
		$tags = parent::cuts("<$tag_selected", $html, '>');
		foreach ($tags as $tag) {
			$tags_with_attrs[] = self::getAttrs($tag);
		}
		return $tags_with_attrs;
	}

	# 1.3.1.1
    public static function getAttrs($tag)
    {
		preg_match_all('/(\w+)="([^"]+)"/', $tag, $matches, PREG_SET_ORDER);
		$attrs = [];
		foreach ($matches as $mat) {
			$attrs[$mat[1]] = $mat[2];
		}
		return $attrs;
	}

    # 1.3.2
    public static function nlToTag($str)
    {
        return str_replace('>', '>' . "\n", $str);
    }

    # 1.3.3
    public static function stripTags($str)
	{
		return preg_replace('/<[^>]*>/', '', $str);
	}

    # 1.3.4
    public static function onlyOneSpace($str)
    {
		$pat = '/ {2,}/';
        $str = preg_replace($pat, " ", $str);
		if (preg_match($pat, $str)) {
			return self::onlyOneSpace($str);
		}
		return $str;
    }

    # 1.3.5
    public static function onlyOneNl($str)
    {
		$pat = '/\s{2,}/';
        $str = preg_replace($pat, "\n", $str);
		if (preg_match($pat, $str)) {
			return self::onlyOneNl($str);
		}
		return $str;
    }

    # 1.3.6
    public static function seeTheUnseen($str)
    {
		$str = str_replace(
			["\n\r", "\r\n", "\r", "\n", "\t", ' '],
			['[NR]', '[RN]', '[R]', '[N]', '[T]', '[ ]'],
			$str
		);
		$str = htmlentities($str);
		return $str;
    }

    # 1.4
	public static function getImages($html, $url)
	{
		# YouTube
		if (is_object($html)) {
			return [$html->thumbnail_url ?? ''];
		}

		# Others
		$src1 = parent::cuts('src="', $html, '"') ?: [];
		$src2 = parent::cuts("src='", $html, "'") ?: [];
		$images = array_merge($src1, $src2);
		#_var::die($images);
		$images = self::onlyImages($images); 
		$images = self::imagesWithDomain($images, $url); 
		$images = self::largerImages($images); 
		return array_filter($images, 'strlen');
	}

    # 1.4.1
    public static function onlyImages($images)
    {
		foreach ($images as $img){
			if (preg_match('/\.js/i', $img)) {
				continue;
			}
			$only_images[] = $img;
		}
		return $only_images;
	}

    # 1.4.2
    public static function imagesWithDomain($images, $url)
    {
		$url = parse_url($url);
		foreach ($images as $img){
			if (preg_match('/^http/i', $img)) {
				$images_with_domain[] = $img;
				continue;
			}
			elseif (str_starts_with($img, '//')) {
				$images_with_domain[] = "https:$img";
				continue;
			}
			$img = ltrim($img, '/');
			$images_with_domain[] = "{$url['scheme']}://{$url['host']}/$img";
		}
		return $images_with_domain;
	}

    # 1.4.3
    public static function largerImages($images)
    {
		foreach ($images as $img){
			if (preg_match('/blogspot/i', $img)) {
				$larger_images[] = str_replace('/s400/', '/s640/', $img);
				continue;
			}
			$larger_images[] = $img;
		}
		return $larger_images;
	}
}
