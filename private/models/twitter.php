<?php
require_once VENDOR_PATH . 'autoload.php';

class Twitter extends LiteRecord
{
    public function enviarTwit($list, $images)
    {
		if (_server::isLocal()) {
			return false;
		}

        $consumer_key        = Config::get('keys.twitter.consumer_key');
        $consumer_secret     = Config::get('keys.twitter.consumer_secret');
        $access_token        = Config::get('keys.twitter.access_token');
        $access_token_secret = Config::get('keys.twitter.access_token_secret');

        $apodo        = Session::get('apodo');
        $titulo       = _html::stripTags($list['titulo']);
        $contenido    = _html::stripTags($list['contenido']);
        $enlace       = 'https://roleplus.app/publicaciones/' . $list['slug'];
        $usuarios_idu = Session::get('idu');

        $image_list  = array_filter(explode(',', $images));
        $image_paths = [];
        foreach ($image_list as $image) {
            $image_paths[] = "img/usuarios/$usuarios_idu/$image";
        }

        $sql = 'SELECT * FROM vistas_previas WHERE donde_idu=?';
        $preview_img = self::first($sql, [$list['idu']]);
        if ($preview_img) {
            $image_paths[] = "img/vistas_previas/$preview_img->idu/$preview_img->image";
        }

        $media_ids = [];
        foreach ($image_paths as $img) {
            if (file_exists($img)) {
                $id = $this->uploadMediaV2($img, $consumer_key, $consumer_secret, $access_token, $access_token_secret);
                if ($id) {
                    $media_ids[] = $id;
                }
            }
        }

        $tweet = "$apodo: $titulo\n$enlace\n$contenido";
        $tweet = $this->truncateTweet($tweet, 280);

        $body = ['text' => $tweet];
        if ($media_ids) {
            $body['media'] = ['media_ids' => $media_ids];
        }

        $result = $this->postV2Tweet(json_encode($body), $consumer_key, $consumer_secret, $access_token, $access_token_secret);

        _mail::toAdmin('Publicando en X', _var::return($result));
    }

    private function uploadMediaV2($filePath, $consumer_key, $consumer_secret, $access_token, $access_token_secret)
    {
        $url = 'https://api.twitter.com/2/media/upload';

        $oauth = [
            'oauth_consumer_key'     => $consumer_key,
            'oauth_token'            => $access_token,
            'oauth_nonce'            => bin2hex(random_bytes(16)),
            'oauth_timestamp'        => time(),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_version'          => '1.0'
        ];
        ksort($oauth);
        $baseString  = 'POST&' . rawurlencode($url) . '&' . rawurlencode(http_build_query($oauth, '', '&', PHP_QUERY_RFC3986));
        $signingKey  = rawurlencode($consumer_secret) . '&' . rawurlencode($access_token_secret);
        $oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));

        $header = 'Authorization: OAuth ';
        $tmp = [];
        foreach ($oauth as $k => $v) {
            $tmp[] = rawurlencode($k) . '="' . rawurlencode($v) . '"';
        }
        $header .= implode(', ', $tmp);

        $postfields = [
            'media_category' => 'tweet_image',
            'media'          => new \CURLFile(realpath($filePath), mime_content_type($filePath), basename($filePath))
        ];

        $res = _curl::post($url, $postfields, [
            'headers' => [$header]
        ]);

        if (!$res->ok || !$res->body) {
            return null;
        }
        $json = json_decode($res->body, true);
        return $json['data']['id'] ?? null;
    }

    private function postV2Tweet($jsonData, $consumer_key, $consumer_secret, $access_token, $access_token_secret)
    {
        $url = 'https://api.twitter.com/2/tweets';

        $oauth = [
            'oauth_consumer_key'     => $consumer_key,
            'oauth_token'            => $access_token,
            'oauth_nonce'            => bin2hex(random_bytes(16)),
            'oauth_timestamp'        => time(),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_version'          => '1.0'
        ];
        ksort($oauth);
        $baseString  = 'POST&' . rawurlencode($url) . '&' . rawurlencode(http_build_query($oauth, '', '&', PHP_QUERY_RFC3986));
        $signingKey  = rawurlencode($consumer_secret) . '&' . rawurlencode($access_token_secret);
        $oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));

        $header = 'Authorization: OAuth ';
        $tmp = [];
        foreach ($oauth as $k => $v) {
            $tmp[] = rawurlencode($k) . '="' . rawurlencode($v) . '"';
        }
        $header .= implode(', ', $tmp);

        $res = _curl::post($url, $jsonData, [
            'headers' => [$header, 'Content-Type: application/json']
        ]);

        $resp = $res->body;
        $http = $res->info['http_code'];

        $decoded = json_decode($resp);
        if (!$decoded) {
            $decoded = (object)['raw_response' => $resp, 'http_code' => $http];
        } else {
            $decoded->http_code = $http;
        }
        return $decoded;
    }

    private function truncateTweet($text, $max = 140)
    {
        $result = '';
        $len = 0;
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($chars as $char) {
            $add = preg_match('/\p{Emoji}/u', $char) ? 2 : 1;
            if ($len + $add > $max - 3) {
                return $result . '...';
            }
            $result .= $char;
            $len += $add;
        }
        return $result;
    }
}
