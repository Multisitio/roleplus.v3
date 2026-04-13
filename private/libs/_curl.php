<?php
/**
 * Librería centralizada para peticiones cURL
 * Evita la duplicación de código y centraliza configuraciones globales como IPv4
 */
class _curl
{
    /**
     * Realiza una petición cURL (GET por defecto)
     * 
     * @param string $url
     * @param array $options Opciones adicionales como 'headers' o 'post'
     * @return object {body, info, error, errno, ok}
     */
    static public function request($url, $options = [])
    {
        $ch = curl_init();

        $defaultOptions = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_ENCODING       => '', // Soporta gzip/deflate automáticamente
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4, // Forzado por estabilidad en el servidor
            CURLOPT_USERAGENT      => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
        ];

        // Manejo especial de cabeceras
        if (isset($options['headers'])) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $options['headers']);
            unset($options['headers']);
        }

        // Manejo especial de POST
        if (isset($options['post'])) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $options['post']);
            unset($options['post']);
        }

        // Aplicamos el resto de opciones
        $finalOptions = $options + $defaultOptions;
        curl_setopt_array($ch, $finalOptions);

        $body = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        return (object)[
            'body'  => $body,
            'info'  => $info,
            'error' => $error,
            'errno' => $errno,
            'ok'    => ($errno === 0 && $info['http_code'] >= 200 && $info['http_code'] < 400)
        ];
    }

    /**
     * Shorthand para GET
     */
    static public function get($url, $options = [])
    {
        return self::request($url, $options);
    }

    /**
     * Shorthand para POST
     */
    static public function post($url, $data, $options = [])
    {
        $options['post'] = $data;
        return self::request($url, $options);
    }

    /**
     * Realiza múltiples peticiones paralelas
     * 
     * @param array $urls Array de URLs o array asociativo [key => url]
     * @param array $options Opciones globales para todas las peticiones
     * @return array Resultados [key => body]
     */
    static public function multi($urls, $options = [])
    {
        $mh = curl_multi_init();
        $handles = [];
        $results = [];

        foreach ($urls as $key => $url) {
            $ch = curl_init();
            $opts = [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
                CURLOPT_USERAGENT      => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
            ];
            
            if (isset($options['headers'])) {
                $opts[CURLOPT_HTTPHEADER] = $options['headers'];
            }

            curl_setopt_array($ch, $options + $opts);
            curl_multi_add_handle($mh, $ch);
            $handles[$key] = $ch;
        }

        $active = null;
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($active > 0);

        foreach ($handles as $key => $ch) {
            $results[$key] = curl_multi_getcontent($ch);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);
        return $results;
    }
}
