<?php
/** Preparacion privada: la salida contiene claves y solo debe circular por tuberia. */
if (PHP_SAPI !== 'cli' || (function_exists('posix_geteuid') && posix_geteuid() === 0)) { exit(1); }
ini_set('display_errors', '0');
ini_set('log_errors', '0');
try {
    define('APP_PATH', rtrim($argv[1], '/\\') . '/');
    require $argv[2];
    $keys = Config::read('keys');
    $push = Config::read('webpush');
    if (!is_array($keys) || !is_array($push) || empty($push['auth']['VAPID']['privateKey'])) {
        throw new RuntimeException('Configuracion no valida');
    }
    if (isset($keys['webpush']) && $keys['webpush'] !== $push) {
        throw new RuntimeException('Configuraciones discrepantes');
    }
    $keys['webpush'] = $push;
    echo json_encode(['source' => "<?php\nreturn " . var_export($keys, true) . ";\n",
        'fingerprint' => hash('sha256', serialize($keys))], JSON_THROW_ON_ERROR);
} catch (Throwable $ignored) {
    exit(1);
}
