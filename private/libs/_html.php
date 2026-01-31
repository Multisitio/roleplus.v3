<?php
/**
 * Dependencias: _str::hashtag
 */
class _html
{
    # De bbcode a html
    static function bbcode($str)
    {
        $str = trim($str);
        
        $str = str_replace('<', '&lt', $str);

        $str = nl2br($str, false);

        $str = preg_replace_callback(
            '/\[([^\]]+)\]/',
            function ($m) {
                $tags = ['b','blockquote','code','h3','h4','i','s','small','sub','sup','table','tr','td','u',
                '/b','/blockquote','/code','/h3','/h4','/i','/s','/small','/sub','/sup','/table','/tr','/td','/u'];

                $tag = in_array($m[1], $tags) ? "<{$m[1]}>" : "[{$m[1]}]";

                $inline = ['/b','/i','/s','/small','/sub','/sup','/u'];

                return in_array($m[1], $inline) ? "$tag<br>" : $tag;
            },
            $str
        );
        

        $str = str_replace('><br>', '>', $str);

        $a[] = "/(♦|♥)/is";
        $b[] = '<span class="red">$1</span>';
        $str = preg_replace($a, $b, $str);

        return $str;
    }

    # De br a nl
    static public function br2nl($str)
    {
        return preg_replace('/\<br(\s*)?\/?\>/i', "\n", $str);
    }

    # Descodifica el html
    static public function hd($str) {
        return $str = html_entity_decode($str, ENT_QUOTES);
    }

    # Enlaces y formatos
	static public function links($s, $css='w3css')
	{
        # Dados
        $s = preg_replace_callback(
            '/\{D([^\d]*)([^:]*):([^\}]+)\}/i',
            function($m) use ($css)
            {
                if ($css == 'w3css') {
                    $s = '<i class="dice size-'.$m[2].' w3-text-'.$m[1].'">'.$m[3].'</i>';
                    return $s;
                }
                $s = '<i class="dice size-'.$m[2].' '.$m[1].'-text">'.$m[3].'</i>';
                return $s;
            },
            $s
        );

        # Menciones
        $s = preg_replace_callback(
            '/({[+|@])([^}]+)(})/',
            function ($m)
            {
                $m[2] = urlencode($m[2]);
                $s = '<a href="/usuarios/perfil/'.$m[2].'">'.$m[0].'</a>';
                return $s;
            },
            $s
        );

        # Enlaces a etiquetas
        $s = preg_replace_callback(
            '/(?:\s|^)(#)([^<\s\.:,]+)/',
            function ($m)
            {
                $m[2] = _str::hashtag($m[2]);
                $s = ' <a class="tag" href="/grupos/ver/'.$m[2].'">'.$m[2].'</a>';
                return $s;
            },
            $s
        );

        # Enlaces a páginas
        $s = preg_replace_callback(
            '/[\w\-:\/;]+(?:\.(?!\.))[^\d<\s][\w\-\.\/#?=&%~;]*/mi',
            function ($m)
            {
                # No tocamos las menciones (No se cumple nunca)
                if (strstr($m[0], '{@')) {
                    return $m[0];
                }
                if (preg_match('/(https)/i', $m[0])) {
                    $m[0] = strstr($m[0], 'https://');
                    $url = $m[0];
                }
                elseif (preg_match('/(http)/i', $m[0])) {
                    $m[0] = strstr($m[0], 'http://');
                    $url = $m[0];
                }
                else {
                    $url = 'https://' . str_ireplace('//', '', $m[0]);
                    $url = strstr($url, 'https://');
                }

                $target_blank = strstr($url, 'roleplus.app') ? '' : ' rel="noopener noreferrer" target="_blank"';
                $s = '<a href="'.$url.'"'.$target_blank.'>'.$m[0].'</a>';
                return $s;
            },
            $s
        );
        return $s;
    }

    static public function nl2br($str)
    {
        return preg_replace('/\R+/', '<br>', $str);
        #return str_replace(["\r\n", "\r", "\n"], '<br>', $str);
    }

    #
    public static function stripTags($str)
	{
        $str = trim($str);
		return preg_replace('/<[^>]*>/', '', $str);
	}

    #
    static public function summary($str)
    {
        $str = self::br2nl($str);
		$str = html_entity_decode($str);
		$str = html_entity_decode($str);
		$str = strip_tags($str);
        $str = trim($str);
		#$str = self::nl2br($str);
		$str = preg_replace('/[^\s]+[^\.\s]\.[^º:<\.\&\s0-9][^<\s]+/i', '[URL]', $str);
        $str = _str::truncate($str, 50);
		$str = str_replace('&nbsp;', ' ', $str);
        $str = trim($str);
        $str = trim($str);
        return $str;
	}
}