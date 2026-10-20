<?php
if (PHP_SAPI !== 'cli' || !function_exists('posix_geteuid') || posix_geteuid() === 0) { exit(1); }
ini_set('display_errors', '0');
ini_set('log_errors', '0');
try {
    $raw = stream_get_contents(STDIN, 262145);
    if (strlen($raw) > 262144) { throw new RuntimeException(); }
    $input = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!preg_match('/^[a-f0-9]{8}$/D', $input['recipient'] ?? '') ||
        !in_array($input['mode'] ?? '', ['resolve', 'send'], true)) { throw new RuntimeException(); }
    define('APP_PATH', '/var/www/clients/client1/web6/private/');
    define('VENDOR_PATH', '/var/www/clients/client1/vendor/');
    require '/var/www/clients/client1/core/kumbia/config.php';
    require APP_PATH . 'libs/_mail.php';
    $_SERVER['HTTP_HOST'] = 'roleplus.app';
    $email = $input['cached_email'] ?? '';
    $fresh = false;
    try {
        $d = Config::get('databases.default');
        $params = $d['params']; $params[PDO::ATTR_TIMEOUT] = 4;
        $pdo = new PDO($d['dsn'], $d['username'], $d['password'], $params);
        $pdo->exec('SET SESSION TRANSACTION READ ONLY'); $pdo->beginTransaction();
        $query = $pdo->prepare('SELECT email FROM usuarios WHERE idu=?');
        $query->execute([$input['recipient']]);
        $email = $query->fetchColumn(); $pdo->rollBack(); $fresh = true;
    } catch (Throwable $ignored) {
    }
    if (!is_string($email) || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException();
    }
    $accepted = false;
    if ($input['mode'] === 'send') {
        if (!is_string($input['body'] ?? null) || strlen($input['body']) > 131072 ||
            !preg_match('/^Vigilante VPS - Resumen(?: inicial)? \d{4}-\d{2}-\d{2}$/D', $input['subject'] ?? '')) {
            throw new RuntimeException();
        }
        $accepted = _mail::send($email, $input['subject'], $input['body'], ['IsText' => true, 'LocalSMTP' => true]);
    }
    echo json_encode(['uid' => posix_geteuid(), 'email' => $email, 'fresh' => $fresh, 'accepted' => $accepted], JSON_THROW_ON_ERROR);
} catch (Throwable $ignored) {
    echo '{"error":"mail_worker_failed"}';
    exit(1);
}
