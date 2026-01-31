<?php
/**
 */
class _link
{
    static public function curl_get_file_contents($url, &$extension = '', $test = 0)
    {
        // Genera una IP aleatoria para la solicitud
        $ip = implode('.', array_map(fn() => rand(0, 255), range(1, 4)));
        $referer = _str::cut('http://', $url, '/');
        
        // Configuración de cURL
        $options = [
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_CONNECTTIMEOUT => 120,
            CURLOPT_ENCODING       => "",
            CURLOPT_HEADER         => true, // Incluir cabeceras en la respuesta
            CURLOPT_HTTPHEADER     => ["REMOTE_ADDR: $ip", "X-Forwarded-For: $ip"],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_REFERER        => $referer,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_URL            => $url,
            CURLOPT_USERAGENT      => $_SERVER['HTTP_USER_AGENT'],
        ];
        
        // Inicialización y ejecución de cURL
        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        
        #error_log("HTTP Code: $http_code, Response Size: " . strlen($response));
        
        if ($http_code > 399) return '';
        
        // Separar cabeceras del contenido
        $headers = substr($response, 0, $header_size);
        $content = substr($response, $header_size);
        
        // Extraer Content-Type desde las cabeceras
        preg_match_all('/Content-Type:\s*([^\s;]+)/i', $headers, $matches);
        $contentTypes = $matches[1] ?? [];
        
        // Si hay múltiples Content-Type, recorrerlos
        $validImageTypes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
            'image/bmp', 'image/tiff', 'image/x-icon', 'image/vnd.microsoft.icon',
            'image/heif', 'image/heif-sequence', 'image/heic', 'image/heic-sequence',
            'image/avif', 'image/jpg'
        ];

        $foundExtension = '';
        
        foreach ($contentTypes as $contentType) {
            // Si el Content-Type es uno de los tipos válidos de imagen
            if (in_array($contentType, $validImageTypes)) {
                // Mapear tipo MIME a extensión
                $mimeToExtension = [
                    'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif',
                    'image/webp' => 'webp', 'image/svg+xml' => 'svg', 'image/bmp' => 'bmp',
                    'image/tiff' => 'tiff', 'image/x-icon' => 'ico', 'image/vnd.microsoft.icon' => 'ico',
                    'image/heif' => 'heif', 'image/heif-sequence' => 'heifs', 'image/heic' => 'heic',
                    'image/heic-sequence' => 'heics', 'image/avif' => 'avif', 'image/jpg' => 'jpg',
                ];
                
                $foundExtension = $mimeToExtension[$contentType] ?? '';
                if ($foundExtension) {
                    break; // Salir del bucle si encontramos una imagen válida
                }
            }
        }
        
        $extension = $foundExtension;
        /*_var::flush("Detected MIME Type(s): " . implode(', ', $contentTypes) . ", Extension: $extension");
        
        if (!$extension) {
            _var::flush("No valid image MIME types found in the Content-Type headers.");
        }*/
        
        return $content;
    }

    static public function curl_put_file_contents($url, $dest, &$extension = '')
    {
        $dir = dirname($dest) . '/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        
        // Obtener el contenido y la extensión del archivo desde la URL
        $content = self::curl_get_file_contents($url, $extension);
        $name = preg_replace('/[?#].*/', '', basename($dest));
        
        // Asegurar que el archivo tenga la extensión correcta
        if ($extension && !str_ends_with($name, ".$extension")) $name .= ".$extension";
        
        $ruta_archivo = "$dir$name";
        if (file_exists($ruta_archivo) && is_readable($ruta_archivo)) return $name;
        
        #_var::flush("Saving file: $ruta_archivo with extension: $extension");
        
        file_put_contents($ruta_archivo, $content);
        return $name;
    }

    static function multiRequest($data, $options = [])
    {
        $mh = curl_multi_init();
        $curly = array_filter($data, fn($url) => preg_match('/^http/i', $url));
        
        foreach ($curly as $url) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT      => $_SERVER['HTTP_USER_AGENT'],
            ] + $options);
            curl_multi_add_handle($mh, $ch);
            $handles[$url] = $ch;
        }
        
        do curl_multi_exec($mh, $running); while ($running > 0);
        
        // Obtener contenido de cada solicitud
        $result = array_map(fn($ch) => curl_multi_getcontent($ch), $handles);
        
        // Cerrar handles de cURL
        array_walk($handles, fn($ch) => curl_multi_remove_handle($mh, $ch));
        curl_multi_close($mh);
        
        return $result;
    }

	# Vista previa
    static public function preview($url, $opt=[])
    {
        if ( ! preg_match('/^http/i', $url)) {
            return false;
        }
        if (stristr($url, 'youtu')) {
            return self::previewYouTube($url);
        }
        $html = self::curl_get_file_contents($url);
        $html = str_replace('<', '&lt;', $html);
        $a['url'] = $url;
        $a['lang'] = substr(mb_strtoupper(_str::cut('html lang="', $html, '"')), 0, 2);
        $a['title'] = _str::cut('title>', $html, '&lt;');
        $a['idu'] = _str::uid($a['title']);
        $a['description'] = _str::cut('meta name="description" content="', $html, '"');
        if ( ! $a['description']) {
            $a['description'] = _str::cut('property="og:description" content="', $html, '"');
        }
        $a['description'] = trim($a['description']);
        if ( ! empty($opt['no_img'])) {
            return $a;
        }

        $in_content = _str::cuts('content="', $html, '"');
        $in_src = [];
        if (empty($opt['solo_imagen_meta'])) {
            $in_src = _str::cuts('src="', $html, '"');
        }
        $srcs = array_merge($in_content, $in_src);
        $a['images'] = [];
        foreach ($srcs as $src) {
            if ( ! preg_match('/\.(gif|jpeg|jpg|png|svg)/i', $src)) {
                continue;
            }
            $hostname = parse_url($url, PHP_URL_HOST);
            if ( ! stristr($src, '//') and ! stristr($src, $hostname)) {
                $src = $hostname . $src;
            }
            $a['images'][] = $src;
        }
        $contents = self::multiRequest($a['images']);

        $src_new = [];
        foreach ($contents as $url=>$content) {
            # Directorio de destino
            $dir_rel = 'img/vistas_previas/' . $a['idu'];
			$host = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
            $dir = "$host/$dir_rel";
            if ( ! realpath($dir) ) {
                mkdir($dir, 0755, 1);
            }
            # Parametros de la URL fuera para el nombre del archivo
            if (stristr($url, '?')) {
                $url = explode('?', $url)[0];
            }
            # Nombre del archivo de destino
            $name = basename($url);
            $name = preg_replace('/[^\d\w\.+]/i', '_', $name);
            if ( ! $name) {
                return;
            }
            # Guardado
            file_put_contents("$dir/$name", $content);
            preg_match('/^(xxs\.|xs\.|s\.|m\.|l\.|xl\.|xxl\.)?(.*)/', $name, $mats);
            $name = $mats[2];
            _img::crearThumbnailRecortado("$dir/$name", "$dir/l.$name", 530);
            #chmod("$dir/l.$name", 0644);
            unlink("$dir/$name");
            $src_new[] = "/$dir_rel/l.$name";
        }
        $a['images'] = $src_new;

        return $a;
    }

	#
    static public function previewYouTube($url)
    {
        if (stristr($url, 'youtu.be')) {
            preg_match('/youtu\.be\/([\d\w]+)/', $url, $mat);
            $id = $mat[1];
            $url = "https://www.youtube.com/watch?v=$id";
        }
        $html = self::curl_get_file_contents("https://www.youtube.com/oembed?url=$url&format=json");
        $youtube = json_decode($html);
        if ( ! is_object($youtube)) {
            return;
        }
        $a['url'] = $url;
        $a['title'] = $youtube->title;
        $a['idu'] = _str::uid($a['title']);
        $a['description'] = $youtube->author_name;

        $url = $youtube->thumbnail_url;
        $content = self::curl_get_file_contents($url);
        # Directorio de destino
        $dir_rel = '/img/vistas_previas/' . $a['idu'];
        $dir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $dir_rel;
        if ( ! realpath($dir) ) {
            mkdir($dir, 0755, 1);
        }
        # Parametros de la URL fuera para el nombre del archivo
        if (stristr($url, '?')) {
            $url = explode('?', $url)[0];
        }
        # Nombre del archivo de destino
        $name = basename($url);
        $name = preg_replace('/[^\d\w\.+]/i', '_', $name);
        if ( ! $name) {
            return;
        }
        # Guardado
        file_put_contents("$dir/$name", $content);
        _img::crearThumbnailRecortado("$dir/$name", "$dir/l.$name", 530);
        chmod("$dir/$name", 0644);
        unlink("$dir/$name");
        $a['images'][] = "$dir_rel/l.$name";

        return $a;
    }
}