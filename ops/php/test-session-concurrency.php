<?php
// Real file-backed sessions, isolated fixtures; no DB, accounts or network.
$app = dirname(__DIR__, 2);
if (is_dir($app . '/private/libs')) { $app .= '/private'; }
define('APP_PATH', $app . '/');
function check($ok, $message) { if ( ! $ok) { throw new RuntimeException($message); } }
if (($argv[1] ?? '') === '--worker') {
    $mode = $argv[2]; $dir = $argv[3]; $sid = $argv[4];
    ini_set('session.save_path', $dir);
    ini_set('session.gc_probability', '0');
    ini_set('session.use_cookies', '0');
    if ($mode !== 'anonymous') { session_id($sid); }
    if ($mode === 'seed') {
        session_start();
        $_SESSION['KUMBIA_SESSION'][APP_PATH] = ['default'=>['idu'=>'fixture-user', 'keep'=>'preserved'], '__meta'=>['cookie_refreshed_at'=>0]];
        session_write_close();
        exit;
    }
    if ($mode === 'write') {
        file_put_contents($dir . '/writer-ready', 'ready');
        $deadline = microtime(true)+10;
        while ( ! is_file($dir . '/running') && microtime(true)<$deadline) { usleep(10000); }
        check(is_file($dir . '/running'), 'Concurrent fixture not running');
    }
    $started = microtime(true);
    require APP_PATH . 'libs/session.php';
    if ($mode === 'anonymous') {
        check(session_status() !== PHP_SESSION_ACTIVE && session_id() === '', 'Anonymous session created');
        check(glob($dir . '/sess_*') === [], 'Anonymous file created');
        Session::set('created-on-write', 'saved');
        check(session_id() !== '' && count(glob($dir . '/sess_*')) === 1 && session_status() !== PHP_SESSION_ACTIVE, 'First anonymous write not saved');
    } elseif ($mode === 'hold') {
        check(Session::get('idu') === 'fixture-user', 'Existing session not read');
        file_put_contents($dir . '/ready', 'ready');
        $deadline = microtime(true)+10;
        while ( ! is_file($dir . '/writer-ready') && microtime(true)<$deadline) { usleep(10000); }
        check(is_file($dir . '/writer-ready'), 'Writer not ready');
        file_put_contents($dir . '/running', 'running');
        usleep(2000000);
        Session::set('first', 'saved');
    } elseif ($mode === 'write') {
        check(session_status() !== PHP_SESSION_ACTIVE, 'Initial lock retained');
        check(Session::get('idu') === 'fixture-user', 'Authentication lost');
        Session::set('second', 'saved');
        Session::setArray('toast', 'one'); Session::setArray('toast', 'two');
        Session::set('temporary', 'value'); Session::delete('temporary');
        Session::set('value', 'test', 'isolated'); Session::deleteAll('isolated');
        check(session_status() !== PHP_SESSION_ACTIVE, 'Write retains lock');
        file_put_contents($dir . '/duration', (string)(microtime(true)-$started));
    } elseif ($mode === 'verify') {
        check(Session::get('first') === 'saved' && Session::get('second') === 'saved', 'Concurrent writes lost');
        check(Session::get('keep') === 'preserved', 'Existing data overwritten');
        check(Session::get('toast') === ['one','two'] && ! Session::has('temporary') && ! Session::has('value', 'isolated'), 'API persistence broken');
        check(Session::get('cookie_refreshed_at', '__meta') >= time()-30, 'Sliding renewal lost');
        Session::open(); check(session_status() === PHP_SESSION_ACTIVE, 'Explicit open broken');
        Session::set('explicit', 'saved'); Session::close();
    } elseif ($mode === 'explicit-verify') {
        check(Session::get('explicit') === 'saved', 'Explicit write not persisted');
        Session::deleteAll();
    } elseif ($mode === 'login') {
        class LiteRecord {}
        require APP_PATH . 'models/usuarios.php';
        class LoginSessionProbe extends Usuarios {
            public function recordarSesion() {
                check(session_status() !== PHP_SESSION_ACTIVE, 'Login keeps lock during remember-cookie query');
                check(Session::get('idu') === 'fixture-login' && Session::get('rol') === 2, 'Incomplete login');
            }
        }
        $method = new ReflectionMethod(Usuarios::class, 'establecerSesion');
        $method->invoke(new LoginSessionProbe(), (object)['idu'=>'fixture-login', 'rol'=>2, 'la_clave'=>'excluded', 'sesion_clave'=>'excluded', 'sesion_valor'=>'excluded']);
        check( ! Session::has('la_clave') && ! Session::has('sesion_clave') && ! Session::has('sesion_valor'), 'Login secrets copied');
        Session::deleteAll();
    } elseif ($mode === 'logout-verify') {
        check( ! Session::get('idu'), 'Logout not persisted');
    }
    echo "OK " . $mode . "\n";
    exit;
}
$dir = sys_get_temp_dir() . '/roleplus-session-test-' . bin2hex(random_bytes(6));
mkdir($dir, 0700); $sid = 'fixture' . bin2hex(random_bytes(12)); $children = [];
function child($mode, $dir, $sid) {
    $process = proc_open([PHP_BINARY, '-n', __FILE__, '--worker', $mode, $dir, $sid], [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $pipes);
    check(is_resource($process), 'Cannot start fixture'); fclose($pipes[0]);
    return [$process, $pipes];
}
function finish($child) {
    [$process, $pipes] = $child; $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); check(proc_close($process) === 0, $out . $err);
}
try {
    finish(child('anonymous', $dir, $sid));
    finish(child('seed', $dir, $sid));
    $holder = child('hold', $dir, $sid); $children[] = $holder;
    $deadline = microtime(true) + 5;
    while ( ! is_file($dir . '/ready') && microtime(true) < $deadline) { usleep(10000); }
    check(is_file($dir . '/ready'), 'Fixture not ready');
    finish(child('write', $dir, $sid)); $elapsed = (float)file_get_contents($dir . '/duration');
    check($elapsed < 1, 'Concurrent request blocked for ' . $elapsed . ' seconds');
    finish($holder); $children = [];
    finish(child('verify', $dir, $sid)); finish(child('explicit-verify', $dir, $sid)); finish(child('login', $dir, $sid)); finish(child('logout-verify', $dir, $sid));
    echo "OK: anonymous without files, concurrent read/write in " . round($elapsed*1000) . " ms while first request lasts 2s, both writes preserved, renewal and logout\n";
} finally {
    foreach ($children as $child) { proc_terminate($child[0]); }
    foreach (glob($dir . '/*') as $file) { unlink($file); }
    rmdir($dir);
}
