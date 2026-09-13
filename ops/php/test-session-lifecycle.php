<?php

if (PHP_SAPI !== 'cli') {
    exit(2);
}

ini_set('session.save_path', sys_get_temp_dir());
ini_set('session.use_cookies', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_samesite', 'Lax');
session_id('roleplus-test-' . bin2hex(random_bytes(8)));

define('APP_PATH', '/roleplus-session-test/');
require dirname(__DIR__, 2) . '/private/libs/session.php';

Session::set('idu', 'test-user');
Session::close();
if (session_status() === PHP_SESSION_ACTIVE) {
    fwrite(STDERR, "El cierre inicial conserva el bloqueo.\n");
    exit(1);
}

Session::setArray('toast', 'saved-after-close');
if (session_status() === PHP_SESSION_ACTIVE) {
    fwrite(STDERR, "Una escritura tardía conserva el bloqueo.\n");
    exit(1);
}

Session::open();
$valid = Session::get('idu') === 'test-user'
    && Session::get('toast')[0] === 'saved-after-close'
    && (int) Session::get('cookie_refreshed_at', '__meta') > 0;

session_destroy();
if (!$valid) {
    fwrite(STDERR, "No se conservaron los datos o la renovación deslizante.\n");
    exit(1);
}

echo "SESSION_LIFECYCLE_OK\n";
