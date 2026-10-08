<?php
// Test the add flow without network requests, a database or a session cookie.
class LiteRecord {
    public static $queries = [];
    public static $existing = false;
    public static function first($sql, $values) { return self::$existing ? (object) ['id' => 1] : null; }
    public static function query($sql, $values) { self::$queries[] = [$sql, $values]; }
}
class Session {
    public static $toasts = [];
    public static function get($key) { return 'test-user'; }
    public static function setArray($key, $value) { self::$toasts[] = $value; }
}
class _link {
    public static $html = '';
    public static $calls = 0;
    public static function curl_get_file_contents($url) { ++self::$calls; return self::$html; }
}
class Rolflix_entradas {
    public static $throw = false;
    public static function cargarSitio($idu, $canal_id) {
        if (self::$throw) { throw new RuntimeException('temporary feed failure'); }
        return 0;
    }
}
function t($s) { return $s; }
require dirname(__DIR__, 2) . '/private/libs/_str.php';
require dirname(__DIR__, 2) . '/private/models/rolflix_sitios.php';
function check($ok, $name) { if ( ! $ok) { throw new RuntimeException($name); } echo "PASS: $name\n"; }
$model = new Rolflix_sitios;
$channelId = 'UCECZvw1IC9gGE4Lz_MKYFhA';
$link = 'https://www.youtube.com/@MigueldeLys';
$html = '"ownerUrls":["http://www.youtube.com/@MigueldeLys"]'
    . '<link href="https://www.youtube.com/feeds/videos.xml?channel_id=' . $channelId . '"';
check($model->incluirSitio(['url' => 'https://example.com/@MigueldeLys']) === false
    && _link::$calls === 0 && count(LiteRecord::$queries) === 0, 'reject unrelated host');
_link::$html = '<html>No feed</html>';
check($model->incluirSitio(['url' => $link]) === false
    && count(LiteRecord::$queries) === 0, 'reject page without channel ID');
_link::$html = $html;
Rolflix_entradas::$throw = true;
check($model->incluirSitio(['url' => $link]) === true
    && count(LiteRecord::$queries) === 1
    && LiteRecord::$queries[0][1][4] === $channelId
    && count(Session::$toasts) > 0, 'keep valid channel when initial feed fails');
LiteRecord::$existing = true;
check($model->incluirSitio(['url' => $link]) === false
    && count(LiteRecord::$queries) === 1, 'avoid duplicate channel');
