<?php
/** Medición de RolePlus sin dependencias del framework ni datos de la petición. */
final class RoleplusRequestMetrics
{
    public static function route(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);
        if ( ! is_string($path)) { return 'other'; }
        $path = preg_replace('#^/index\.php(?=/|$)#', '', $path);
        if ($path === '' || $path === '/') { return '/'; }
        // Etiquetas cerradas: nunca guardar IDs, nombres, tokens o query strings.
        $routes = ['/ev/manuales/ia_html', '/ev/manuales/editor', '/ev/manuales/formulario_regla',
            '/usuarios/identificarse', '/usuarios/formularios', '/usuarios/registrarse',
            '/usuarios/recordar_clave', '/usuarios/confirmar', '/usuarios/perfil',
            '/registrados/publicaciones', '/registrados/comentarios', '/registrados/conversaciones',
            '/registrados/personajes', '/registrados', '/ev', '/atalaya',
            '/img/usuarios', '/img/rss', '/img/icons', '/img', '/imagenes',
            '/publicaciones/buscar', '/publicaciones', '/usuarios', '/buscador', '/chat'];
        foreach ($routes as $route) {
            if ($path === $route || str_starts_with($path, $route . '/')) { return $route; }
        }
        return 'other';
    }

    public static function collect(float $started, float $finished): array
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        $status = http_response_code() ?: 200;
        $milliseconds = max(0, round(($finished - $started) * 1000, 3));
        $app = defined('APP_PATH') ? APP_PATH : '';
        return ['time_utc' => gmdate('Y-m-d\TH:i:s\Z'), 'pid' => getmypid(),
            'route' => self::route($_SERVER['REQUEST_URI'] ?? ''),
            'method' => in_array($method, ['GET', 'POST', 'HEAD', 'OPTIONS', 'PUT', 'PATCH', 'DELETE'], true) ? $method : 'OTHER',
            'http' => $status, 'php_ms' => $milliseconds,
            'peak_kib' => (int) ceil(memory_get_peak_usage(true) / 1024),
            'authenticated' => ! empty($_SESSION['KUMBIA_SESSION'][$app]['default']['idu']),
            'sample_every' => ($milliseconds >= 1000 || $status >= 500) ? 1 : 20];
    }

    public static function start(): void
    {
        $started = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        register_shutdown_function(static function () use ($started) {
            // La instrumentación nunca debe impedir la respuesta de la aplicación.
            try {
                $record = self::collect($started, microtime(true));
                if ($record['sample_every'] !== 1 && mt_rand(1, $record['sample_every']) !== 1) { return; }
                $app = defined('APP_PATH') ? APP_PATH : dirname(__DIR__, 2) . '/';
                @error_log(json_encode($record, JSON_UNESCAPED_SLASHES) . "\n", 3,
                    $app . 'var/observability/requests.jsonl');
            } catch (Throwable $ignored) {
                // Sin mensajes que puedan incluir información de la petición.
            }
        });
    }
}

if (PHP_SAPI !== 'cli') {
    RoleplusRequestMetrics::start();
    // Conservar exactamente el WAF ya activo; solo este pool usa el wrapper.
    require '/usr/share/php/global_waf.php';
}
