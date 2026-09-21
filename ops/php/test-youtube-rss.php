<?php
// Standalone regression test: no network, database or email.
class LiteRecord {}
class _curl {
    public static $responses = [];
    public static $calls = 0;
    public static function get($url, $options) {
        ++self::$calls;
        if (strpos($url, 'channel_id=UCtest') === false || $options[CURLOPT_USERAGENT] !== 'Mozilla/5.0') {
            throw new RuntimeException('Unexpected request');
        }
        return array_shift(self::$responses);
    }
}
require dirname(__DIR__, 2) . '/private/models/rolflix_entradas.php';
class FeedProbe extends Rolflix_entradas {
    public $waits = [];
    protected function esperarReintentoRss($attempt) { $this->waits[] = $attempt; }
    public function fetch() { return $this->obtenerFeedYoutube('UCtest'); }
}
function response($code, $body = '', $errno = 0) {
    return (object) ['ok' => $code === 200 && $errno === 0, 'info' => ['http_code' => $code], 'errno' => $errno, 'error' => '', 'body' => $body];
}
$feed = '<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"><title>Empty but valid</title></feed>';
$cases = [
    'success' => [[response(200, $feed)], $feed, 1],
    'transient errors' => [[response(404), response(500), response(200, $feed)], $feed, 3],
    'bounded failure' => [[response(404), response(404), response(404)], false, 3],
    'timeout and rate limit' => [[response(0, '', 28), response(429), response(200, $feed)], $feed, 3],
    'HTML is not an empty feed' => [[response(200, '<html/>'), response(200, $feed)], $feed, 2],
    'invalid XML' => [[response(200, '<feed>'), response(200, $feed)], $feed, 2],
    'permanent error' => [[response(403)], false, 1],
];
foreach ($cases as $name => [$responses, $expected, $calls]) {
    _curl::$responses = $responses;
    _curl::$calls = 0;
    $probe = new FeedProbe;
    if ($probe->fetch() !== $expected || _curl::$calls !== $calls || count($probe->waits) !== $calls - 1) {
        throw new RuntimeException('Failed: ' . $name);
    }
    echo 'PASS: ' . $name . PHP_EOL;
}
