<?php
/** Transporte CLI sin privilegios. Reutiliza Config y Minishlink de ROLEplus. */
if (PHP_SAPI !== 'cli' || !function_exists('posix_geteuid') || posix_geteuid() === 0) {
    exit(1);
}
ini_set('display_errors', '0');
ini_set('log_errors', '0');
try {
    $input = stream_get_contents(STDIN, 131073);
    if (strlen($input) > 131072) { throw new RuntimeException('Entrada excesiva'); }
    $request = json_decode($input, true, 32, JSON_THROW_ON_ERROR);
    if (!preg_match('/^[a-f0-9]{8}$/D', $request['recipient'] ?? '') ||
        !in_array($request['mode'] ?? '', ['refresh', 'send'], true)) {
        throw new RuntimeException('Operacion no valida');
    }
    define('APP_PATH', '/var/www/clients/client1/web6/private/');
    require '/var/www/clients/client1/core/kumbia/config.php';
    require '/var/www/clients/client1/vendor/autoload.php';
    $subscriptions = $request['cached_subscriptions'] ?? [];
    $fresh = false;
    try {
        $d = Config::get('databases.default');
        $params = $d['params'];
        $params[PDO::ATTR_TIMEOUT] = 4;
        $pdo = new PDO($d['dsn'], $d['username'], $d['password'], $params);
        $pdo->exec('SET SESSION TRANSACTION READ ONLY');
        $pdo->beginTransaction();
        $q = $pdo->prepare('SELECT suscripcion, p256dh, auth FROM suscripciones WHERE usuarios_idu=? AND browser LIKE ? LIMIT 6');
        $q->execute([$request['recipient'], '%Android%']);
        $subscriptions = $q->fetchAll(PDO::FETCH_ASSOC);
        $pdo->rollBack();
        $fresh = true;
    } catch (Throwable $ignored) {
        // Una averia SQL no impide usar la ultima suscripcion privada conocida.
    }
    if (!is_array($subscriptions) || count($subscriptions) > 5) {
        throw new RuntimeException('Destinatarios no validos');
    }
    foreach ($subscriptions as $s) {
        $url = parse_url($s['suscripcion']);
        if (($url['scheme'] ?? '') !== 'https' || ($url['host'] ?? '') !== 'fcm.googleapis.com' ||
            isset($url['user']) || isset($url['pass']) || isset($url['port']) ||
            strlen($s['suscripcion']) > 4096 || strlen($s['p256dh']) > 256 || strlen($s['auth']) > 128) {
            throw new RuntimeException('Suscripcion no valida');
        }
    }
    $reports = [];
    if ($request['mode'] === 'send') {
        $body = $request['body'] ?? '';
        if (!is_string($body) || strlen($body) > 1600 || $body === '') {
            throw new RuntimeException('Texto no valido');
        }
        $reference = $request['reference'] ?? '';
        if ($reference !== 'prueba' && !preg_match('/^[a-f0-9]{64}$/D', $reference)) {
            throw new RuntimeException('Referencia no valida');
        }
        $payload = json_encode([
            'title' => 'Vigilante del VPS', 'body' => $body,
            'url' => 'https://roleplus.app/registrados/vigilante/aviso/' . $reference,
            'icon' => '/img/logos/icon-192x192.png', 'badge' => '/img/logos/badge.png',
            'requireInteraction' => true,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $webpush = new Minishlink\WebPush\WebPush(Config::get('webpush.auth'),
            ['TTL' => 300, 'urgency' => 'high'], 12,
            ['allow_redirects' => false, 'connect_timeout' => 4]);
        foreach ($subscriptions as $s) {
            try {
                $subscription = Minishlink\WebPush\Subscription::create([
                    'endpoint' => $s['suscripcion'], 'publicKey' => $s['p256dh'], 'authToken' => $s['auth'],
                ]);
                $report = $webpush->sendOneNotification($subscription, $payload);
                $reports[] = ['accepted' => $report->isSuccess(),
                    'status' => $report->getResponse() ? $report->getResponse()->getStatusCode() : 0];
            } catch (Throwable $ignored) {
                $reports[] = ['accepted' => false, 'status' => 0];
            }
        }
    }
    // Solo el coordinador administrador lee esta salida privada por una tuberia.
    echo json_encode(['version' => 1, 'uid' => posix_geteuid(), 'fresh' => $fresh,
        'subscriptions' => $subscriptions, 'reports' => $reports], JSON_THROW_ON_ERROR);
} catch (Throwable $ignored) {
    echo '{"error":"worker_failed"}';
    exit(1);
}
