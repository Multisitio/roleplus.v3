<?php
/**
 */
class _link
{
    static public function curl_get_file_contents($url, &$extension = '', $test = 0)
    {
        $ip = rand(0, 255) . "." . rand(0, 255) . "." . rand(0, 255) . "." . rand(0, 255);
        $referer = _str::cut("http://", $url, "/");
        $res = _curl::get($url, [
            CURLOPT_CONNECTTIMEOUT => 120,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_REFERER => $referer,
            "headers" => ["X-Forwarded-For: $ip"]
        ]);
        if (!$res->ok) return "";
        $contentTypeHeader = $res->info["content_type"] ?? "";
        $contentTypes = [];
        if ($contentTypeHeader) {
            $parts = explode(";", $contentTypeHeader);
            $contentTypes[] = trim($parts[0]);
        }
        $validImageTypes = [
            "image/jpeg", "image/png", "image/gif", "image/webp", "image/svg+xml",
            "image/bmp", "image/tiff", "image/x-icon", "image/vnd.microsoft.icon",
            "image/heif", "image/heic", "image/avif", "image/jpg"
        ];
        $foundExtension = "";
        foreach ($contentTypes as $contentType) {
            if (in_array($contentType, $validImageTypes)) {
                $mimes = [
                    "image/jpeg" => "jpg", "image/png" => "png", "image/gif" => "gif",
                    "image/webp" => "webp", "image/svg+xml" => "svg", "image/bmp" => "bmp",
                    "image/tiff" => "tiff", "image/x-icon" => "ico", "image/vnd.microsoft.icon" => "ico",
                    "image/heif" => "heif", "image/heic" => "heic", "image/avif" => "avif", "image/jpg" => "jpg",
                ];
                $foundExtension = $mimes[$contentType] ?? "";
                if ($foundExtension) break;
            }
        }
        $extension = $foundExtension;
        return $res->body;
    }

    static public function curl_put_file_contents($url, $dest, &$extension = "")
    {
        $dir = dirname($dest) . "/";
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $content = self::curl_get_file_contents($url, $extension);
        $name = preg_replace("/[?#].*/", "", basename($dest));
        if ($extension && !str_ends_with($name, ".$extension")) $name .= ".$extension";
        $path = "$dir$name";
        if (file_exists($path) && is_readable($path)) return $name;
        file_put_contents($path, $content);
        return $name;
    }

    static public function multiRequest($data, $options = [])
    {
        $items = array_filter($data, function($u) {
            return preg_match("/^http/i", $u);
        });
        return _curl::multi($items, $options);
    }

    static public function preview($url, $opt = [])
    {
        if (!preg_match("/^http/i", $url)) return false;
        if (stristr($url, "youtu")) return self::previewYouTube($url);
        $html = self::curl_get_file_contents($url);
        $html = str_replace("<", "&lt;", $html);
        $a = [];
        $a["url"] = $url;
        $a["lang"] = substr(mb_strtoupper(_str::cut("html lang=\"", $html, "\"")), 0, 2);
        $a["title"] = _str::cut("title>", $html, "&lt;");
        $a["idu"] = _str::uid($a["title"]);
        $a["description"] = trim(_str::cut("meta name=\"description\" content=\"", $html, "\"") ?: _str::cut("property=\"og:description\" content=\"", $html, "\""));
        if (!empty($opt["no_img"])) return $a;
        $in_content = _str::cuts("content=\"", $html, "\"");
        $in_src = empty($opt["solo_imagen_meta"]) ? _str::cuts("src=\"", $html, "\"") : [];
        $srcs = array_merge($in_content, $in_src);
        $a["images"] = [];
        foreach ($srcs as $src) {
            if (!preg_match("/\.(gif|jpeg|jpg|png|svg)/i", $src)) continue;
            $host = parse_url($url, PHP_URL_HOST);
            if (!stristr($src, "//") && !stristr($src, $host)) $src = $host . $src;
            $a["images"][] = $src;
        }
        $contents = self::multiRequest($a["images"]);
        $src_new = [];
        foreach ($contents as $u => $c) {
            $dir_rel = "img/vistas_previas/" . $a["idu"];
            $dir = rtrim($_SERVER["DOCUMENT_ROOT"], "/") . "/" . $dir_rel;
            if (!realpath($dir)) mkdir($dir, 0755, true);
            $file_url = stristr($u, "?") ? explode("?", $u)[0] : $u;
            $name = preg_replace("/[^\d\w\.+]/i", "_", basename($file_url));
            if (!$name) continue;
            file_put_contents("$dir/$name", $c);
            _img::crearThumbnailRecortado("$dir/$name", "$dir/l.$name", 530);
            unlink("$dir/$name");
            $src_new[] = "/$dir_rel/l.$name";
        }
        $a["images"] = $src_new;
        return $a;
    }

    static public function previewYouTube($url)
    {
        if (stristr($url, "youtu.be")) {
            preg_match("/youtu\.be\/([\d\w]+)/", $url, $mat);
            $url = "https://www.youtube.com/watch?v=" . ($mat[1] ?? "");
        }
        $res = _curl::get("https://www.youtube.com/oembed?url=$url&format=json");
        $yt = json_decode($res->body);
        if (!is_object($yt)) return null;
        $a = [];
        $a["url"] = $url;
        $a["title"] = $yt->title;
        $a["idu"] = _str::uid($a["title"]);
        $a["description"] = $yt->author_name;
        $thumb_url = $yt->thumbnail_url;
        $res_thumb = _curl::get($thumb_url);
        $dir_rel = "/img/vistas_previas/" . $a["idu"];
        $dir = rtrim($_SERVER["DOCUMENT_ROOT"], "/") . $dir_rel;
        if (!realpath($dir)) mkdir($dir, 0755, true);
        $file_url = stristr($thumb_url, "?") ? explode("?", $thumb_url)[0] : $thumb_url;
        $name = preg_replace("/[^\d\w\.+]/i", "_", basename($file_url));
        if ($name) {
            file_put_contents("$dir/$name", $res_thumb->body);
            _img::crearThumbnailRecortado("$dir/$name", "$dir/l.$name", 530);
            unlink("$dir/$name");
            $a["images"][] = "$dir_rel/l.$name";
        }
        return $a;
    }
}
