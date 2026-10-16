<?php
// Cargas repetidas sin red, correos ni base de datos real.
class LiteRecord
{
    public static $rows = [];
    public static function all($sql, $params)
    {
        return array_map(function ($url) { return (object) ['url' => $url]; }, self::$rows);
    }
    public static function query($sql, $params)
    {
        if (strpos($sql, 'DELETE FROM rss_entradas') === 0) {
            self::$rows = [];
        } elseif (strpos($sql, 'INSERT INTO rss_entradas') === 0) {
            foreach (array_chunk($params, 8) as $entry) {
                if (count($entry) !== 8) {
                    throw new RuntimeException('Entrada incompleta');
                }
                self::$rows[] = $entry[6];
            }
        }
    }
}
class Session { public static function setArray($key, $value) {} }
class _link {
    public static $feed = '';
    public static function curl_get_file_contents($url) { return self::$feed; }
}
class _html { public static function summary($text) { return $text; } }
function t($text) { return $text; }
require dirname(__DIR__, 2) . '/private/libs/_str.php';
require dirname(__DIR__, 2) . '/private/models/rss_entradas.php';
function feed($ids, $atom = false, $title = 'Entrada')
{
    $xml = $atom ? '<feed><title>Blog</title>' : '<rss><channel><title>Blog</title>';
    foreach ($ids as $id) {
        $xml .= $atom
            ? '<entry><title>' . $title . $id . '</title><summary>Texto</summary><link rel="alternate" href="https://example.test/' . $id . '"/><published>2026-10-16T00:00:00Z</published></entry>'
            : '<item><title>' . $title . $id . '</title><description>Texto</description><link>https://example.test/' . $id . '</link><pubDate>Fri, 16 Oct 2026 00:00:00 GMT</pubDate></item>';
    }
    return $xml . ($atom ? '</feed>' : '</channel></rss>');
}
function check($condition, $message)
{
    if ( ! $condition) { throw new RuntimeException($message); }
    echo "PASS: $message\n";
}
foreach ([false, true] as $atom) {
    LiteRecord::$rows = [];
    $model = new Rss_entradas;
    _link::$feed = feed(range(1, 10), $atom);
    check($model->cargarSitio('blog', 'https://example.test/feed') === 10, 'Primera carga: 10');
    check($model->cargarSitio('blog', 'https://example.test/feed') === 0, 'Mismo feed: 0');
    _link::$feed = feed(range(1, 10), $atom, 'Título corregido');
    check($model->cargarSitio('blog', 'https://example.test/feed') === 0, 'Mismos enlaces con títulos editados: 0');
    _link::$feed = feed(range(2, 11), $atom);
    check($model->cargarSitio('blog', 'https://example.test/feed') === 1 && count(LiteRecord::$rows) === 10, 'Una incorporación: 1; conserva el límite de 10');
    check($model->cargarSitio('blog', 'https://example.test/feed') === 0, 'Repetir actualización: 0');
    $before = LiteRecord::$rows;
    _link::$feed = '';
    check($model->cargarSitio('blog', 'https://example.test/feed') === null && LiteRecord::$rows === $before, 'Fallo de descarga: conserva las entradas');
}
