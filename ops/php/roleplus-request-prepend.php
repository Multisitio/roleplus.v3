<?php
/** MediciÃ³n de RolePlus sin dependencias del framework ni datos de la peticiÃ³n. */
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
            '/registrados/personajes', '/registrados/youtube', '/registrados', '/ev', '/atalaya',
            '/img/usuarios', '/img/rss', '/img/icons', '/img', '/imagenes',
            '/publicaciones/buscar', '/publicaciones', '/usuarios', '/buscador', '/chat'];
        foreach ($routes as $route) {
            if ($path === $route || str_starts_with($path, $route . '/')) { return $route; }
        }
        return 'other';
    }

    public static function beforePhp(float $started): ?float
    {
        $header = $_SERVER['HTTP_X_ROLEPLUS_REQUEST_START'] ?? null;
        if ( ! is_string($header) || ! preg_match('/\At=([0-9]{1,19})\z/', $header, $match)) {
            return null;
        }
        $received = (float) $match[1] / 1000000;
        $elapsed = $started - $received;
        // Valor ausente/invalido no se convierte en una espera de cero.
        if ($received <= 0 || $elapsed < 0 || $elapsed > 600) {
            return null;
        }
        return round($elapsed * 1000, 3);
    }

    public static function collect(float $started, float $finished): array
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        $status = http_response_code() ?: 200;
        $milliseconds = max(0, round(($finished - $started) * 1000, 3));
        $app = defined('APP_PATH') ? APP_PATH : '';
        $before = self::beforePhp($started);
        $route = self::route($_SERVER['REQUEST_URI'] ?? '');
        return ['time_utc' => gmdate('Y-m-d\TH:i:s\Z'), 'pid' => getmypid(),
            'route' => $route,
            'method' => in_array($method, ['GET', 'POST', 'HEAD', 'OPTIONS', 'PUT', 'PATCH', 'DELETE'], true) ? $method : 'OTHER',
            'http' => $status, 'php_ms' => $milliseconds,
            'pre_php_ms' => $before,
            'server_to_php_end_ms' => $before === null ? null : round($before + $milliseconds, 3),
            'peak_kib' => (int) ceil(memory_get_peak_usage(true) / 1024),
            'authenticated' => ! empty($_SESSION['KUMBIA_SESSION'][$app]['default']['idu']),
            'sample_every' => ($milliseconds >= 1000 || $status >= 500
                || ($before !== null && $before >= 1000) || $route === '/ev/manuales/ia_html') ? 1 : 20];
    }

    public static function start(): void
    {
        $started = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        register_shutdown_function(static function () use ($started) {
            // La instrumentaciÃ³n nunca debe impedir la respuesta de la aplicaciÃ³n.
            try {
                $record = self::collect($started, microtime(true));
                if ($record['sample_every'] !== 1 && mt_rand(1, $record['sample_every']) !== 1) { return; }
                $app = defined('APP_PATH') ? APP_PATH : dirname(__DIR__, 2) . '/';
                @error_log(json_encode($record, JSON_UNESCAPED_SLASHES) . "\n", 3,
                    $app . 'var/observability/requests.jsonl');
            } catch (Throwable $ignored) {
                // Sin mensajes que puedan incluir informaciÃ³n de la peticiÃ³n.
            }
        });
    }
}

if (PHP_SAPI !== 'cli') {
    RoleplusRequestMetrics::start();
    // Conservar exactamente el WAF ya activo; solo este pool usa el wrapper.
    require '/usr/share/php/global_waf.php';
}
