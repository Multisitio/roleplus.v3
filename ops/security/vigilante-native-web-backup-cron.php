<?php
/** Una copia manual del sitio revisado, usando el ejecutor instalado de ISPConfig. */
if (PHP_SAPI !== 'cli' || posix_geteuid() !== 0 || $argc !== 2
    || !ctype_digit($argv[1]) || (int) $argv[1] < 1) {
    exit(2);
}
umask(0077);
define('SCRIPT_PATH', '/usr/local/ispconfig/server');
require SCRIPT_PATH . '/lib/config.inc.php';
require SCRIPT_PATH . '/lib/app.inc.php';
$app->load('backup');
// Conservar cron.php en el nombre: el cron comprueba tambien /proc/PID/cmdline.
// Un fallo abrupto puede dejar el bloqueo: revisar el PID antes de retirarlo.
$lock = $conf['temppath'] . $conf['fs_div'] . '.ispconfig_cron_lock';
$deadline = microtime(true) + 90;
do {
    $handle = @fopen($lock, 'x');
    if ($handle) {
        break;
    }
    usleep(500000);
} while (microtime(true) < $deadline);
if (!$handle) {
    throw new RuntimeException('El cron del panel esta ocupado');
}
fwrite($handle, (string) getmypid());
fclose($handle);
try {
    if (!backup::run_backup((int) $argv[1], 'web', 'manual')) {
        throw new RuntimeException('La copia del panel no se completo');
    }
} finally {
    if (trim((string) file_get_contents($lock)) === (string) getmypid()) {
        unlink($lock);
    }
}
echo json_encode(['completed' => true, 'domain_id' => (int) $argv[1]]) . "\n";
