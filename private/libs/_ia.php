<?php
/**
 * _ia: Librería para interactuar con IA (Gemini)
 * ✦ Si el prompt contiene una URL de canal YouTube (/@, /channel/, /c/) y/o /videos:
 * - Hace fetch directo de la página (cURL) y extrae el primer videoId de ytInitialData.
 * - Devuelve el enlace real al último vídeo. Si falla, dice "No he encontrado fuentes fiables."
 * ✦ En otros casos: usa Google Gemini API.
 */
class _ia
{
    public static function ask($prompt, $role = '', $name = '')
    {
        ini_set('max_execution_time', 120);

        // 1) INTENTO DETERMINISTA: URL de YouTube canal / videos
        $url = self::firstUrl($prompt);
        if ($url && self::isYouTubeChannelUrl($url)) {
            $videosUrl = self::ensureVideosTab($url);
            $html = self::httpGet($videosUrl);
            
            if (is_string($html) && $html !== '') {
                $videoId = self::extractLatestYouTubeVideoId($html);
                if ($videoId) {
                    return (object)[
                        'output_text' => 'https://www.youtube.com/watch?v=' . $videoId,
                        '_source' => 'deterministic_youtube_scrape',
                        '_citations' => [$videosUrl]
                    ];
                }
            }
            
            return (object)[
                'output_text' => 'No he encontrado fuentes fiables.',
                '_error' => 'youtube_parse_failed',
                '_citations' => [$videosUrl]
            ];
        }

        // 2) GEMINI API
        $key = Config::get('keys.gemini.token');
        if (!$key || $key === 'TODO_SET_GEMINI_KEY') {
            return (object)[
                'output_text' => 'Falta la configuración de la clave de Gemini en keys.php.',
                '_error' => 'missing_config'
            ];
        }

        $instructions = 'Responde de forma clara y concisa. Si te piden buscar algo real, intenta proporcionar datos precisos.';
        if ($role) {
            $instructions = $role . "\n" . $instructions;
        }

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => ($name ? "Nombre del usuario: $name\n" : "") . $prompt]
                    ]
                ]
            ],
            'systemInstruction' => [
                'parts' => [
                    ['text' => $instructions]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 8192,
            ]
        ];

        $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent?key=" . $key;
        
        $res = self::postJson($apiUrl, ['Content-Type: application/json'], $payload);
        
        if ($res['ok'] && !empty($res['json']->candidates[0]->content->parts[0]->text)) {
            return (object)[
                'output_text' => $res['json']->candidates[0]->content->parts[0]->text,
                '_source' => 'gemini_api'
            ];
        }

        return (object)[
            'output_text' => 'No he encontrado fuentes fiables o la IA no respondió correctamente.',
            '_error' => 'ia_unavailable',
            '_raw' => $res['raw']
        ];
    }

    private static function httpGet($url)
    {
        $res = _curl::get($url, [
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 20,
            'headers'              => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
                'Accept-Language: es-ES,es;q=0.9,en;q=0.8'
            ]
        ]);
        return $res->body;
    }

    private static function firstUrl($text)
    {
        if (!is_string($text) || $text === '') return '';
        if (preg_match('#https?://\S+#', $text, $m)) {
            return rtrim($m[0], ').,;\'"');
        }
        return '';
    }

    private static function isYouTubeChannelUrl($url)
    {
        return (bool)preg_match('#^https?://(www\.)?youtube\.com/(?:@[^/]+|channel/[^/]+|c/[^/]+)(?:/videos)?#i', $url);
    }

    private static function ensureVideosTab($url)
    {
        if (preg_match('#/videos/?$#i', $url)) return $url;
        return rtrim($url, '/') . '/videos';
    }

    private static function extractLatestYouTubeVideoId($html)
    {
        $json = '';
        if (preg_match('#var\s+ytInitialData\s*=\s*(\{.*?\});#s', $html, $m)) {
            $json = $m[1];
        } elseif (preg_match('#"ytInitialData"\s*:\s*(\{.*?\})[,<]#s', $html, $m)) {
            $json = $m[1];
        }
        if ($json === '') return '';

        $data = json_decode(self::relaxJson($json), true);
        if (!is_array($data)) return '';

        return self::findFirstVideoId($data);
    }

    private static function relaxJson($json)
    {
        $json = preg_replace("#,\s*}#s", "}", $json);
        $json = preg_replace("#,\s*]#s", "]", $json);
        return $json;
    }

    private static function findFirstVideoId($node)
    {
        if (is_array($node)) {
            if (isset($node['videoRenderer']['videoId']) && is_string($node['videoRenderer']['videoId']) && $node['videoRenderer']['videoId'] !== '') {
                return $node['videoRenderer']['videoId'];
            }
            if (isset($node['richItemRenderer']['content']['videoRenderer']['videoId'])) {
                $vid = $node['richItemRenderer']['content']['videoRenderer']['videoId'];
                if (is_string($vid) && $vid !== '') return $vid;
            }
            foreach ($node as $v) {
                $id = self::findFirstVideoId($v);
                if ($id) return $id;
            }
        }
        return '';
    }

    private static function postJson($url, $headers, $data)
    {
        $res = _curl::post($url, json_encode($data), [
            'headers' => $headers,
            CURLOPT_TIMEOUT => 60
        ]);

        $raw = $res->body;
        $http = $res->info['http_code'];
        if ($res->error) {
            $raw .= ' Error:' . $res->error;
        }

        $json = json_decode($raw);
        
        if (!$res->ok) {
            _mail::toAdmin('_ia::ask[http '.$http.']', '<pre>'.$raw);
        }

        return [
            'ok' => ($res->ok && $json),
            'http' => $http,
            'raw' => $raw,
            'json' => $json
        ];
    }
}
