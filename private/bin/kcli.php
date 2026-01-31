<?php
chdir('/var/www/clients/client1/roleplus.app/web/');
echo 'En el directorio: ' . getcwd() . PHP_EOL;

/* =====================
 * Entorno simulado web mínimo para CLI
 * ===================== */
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_USER_AGENT'] = 'Chrome X';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'Scripts cron';
$_SERVER['SERVER_PROTOCOL'] = 'CLI/1.0';
$_SERVER['SERVER_SOFTWARE'] = 'php-cli';

/* Constantes de app */
const APP_CHARSET = 'UTF-8';
const APP_PATH = '/var/www/clients/client1/roleplus.app/private/';
const CMD_PATH = '/usr/local/bin/';
const CORE_PATH = '/var/www/clients/client1/core/';
const DOMAIN = 'https://roleplus.app/';
const PRODUCTION = false;
const PUB_PATH = '/var/www/clients/client1/roleplus.app/web/';
const PUBLIC_PATH = '/';
const VENDOR_PATH = '/var/www/clients/client1/vendor/';

/* =====================
 * Utilidades de log (stdout)
 * ===================== */
function kcli_log($msg) {
	echo '[kcli] ' . date('Y-m-d H:i:s') . ' ' . $msg . PHP_EOL;
}

/* =====================
 * Polyfills seguros para CLI
 * ===================== */
if (!function_exists('http_response_code')) {
	function http_response_code($code = null) {
		static $stored = 200;
		if ($code !== null) {
			$stored = (int)$code;
		}
		return $stored;
	}
}
if (!function_exists('session_status')) {
	if (!defined('PHP_SESSION_NONE')) define('PHP_SESSION_NONE', 1);
	if (!defined('PHP_SESSION_ACTIVE')) define('PHP_SESSION_ACTIVE', 2);
	function session_status() { return PHP_SESSION_NONE; }
	function session_start(array $options = []) { return true; }
	function session_id($id = null) { static $sid = 'CLI'; if ($id !== null) $sid = (string)$id; return $sid; }
	function session_write_close() { return true; }
	function session_regenerate_id($delete_old_session = false) { return true; }
	function session_name($name = null) { static $n = 'SID'; if ($name !== null) $n = (string)$name; return $n; }
}

/* =====================
 * URL desde argv
 * ===================== */
if (isset($argv) && isset($argv[1]) && $argv[1]) {
	$url = $argv[1];
	$_SERVER['REQUEST_URI'] = $argv[1];
} else {
	exit("No has pasado la url\n");
}

/* =====================
 * Métricas y captura
 * ===================== */
$time_start = microtime(true);
$mem_start = memory_get_usage();
kcli_log('URL: ' . $url);
kcli_log('Inicio');

/* Capturamos cualquier salida de Kumbia (idealmente 0 bytes en acciones sin vista) */
ob_start();

/* Registrar un shutdown para reportar fatales también */
register_shutdown_function(function () use ($time_start, $mem_start) {
	$err = error_get_last();
	$buf = ob_get_contents();
	$bytes = $buf !== false ? strlen($buf) : 0;
	if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
		kcli_log('FATAL: ' . $err['message'] . ' (' . $err['file'] . ':' . $err['line'] . ')');
	}
	$elapsed_ms = (int)round((microtime(true) - $time_start) * 1000);
	$peak_mb = number_format(memory_get_peak_usage() / 1048576, 3);
	$code = http_response_code();
	kcli_log('Código: ' . $code);
	kcli_log('Salida capturada: ' . $bytes . ' bytes');
	kcli_log('Tiempo: ' . $elapsed_ms . ' ms');
	kcli_log('Memoria pico: ' . $peak_mb . ' MB');
	/* Si hubo salida, la mostramos después del resumen para no ensuciar la lectura */
	if ($bytes > 0 && $buf !== false) {
		kcli_log('--- Comienzo de salida Kumbia ---');
		/* Mostramos como está (puede ser HTML si hubo excepción de framework) */
		echo $buf;
		kcli_log('--- Fin de salida Kumbia ---');
		/* Limpiamos el buffer original para evitar duplicados */
		ob_end_clean();
	} else {
		/* No había salida: limpiamos el buffer silenciosamente */
		if (ob_get_level() > 0) { ob_end_clean(); }
	}
	kcli_log('Fin');
});

/* =====================
 * Bootstrap Kumbia
 * ===================== */
require CORE_PATH . 'kumbia/bootstrap.php';
