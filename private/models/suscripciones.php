<?php
require_once VENDOR_PATH . 'autoload.php';

use Kumbia\ActiveRecord\LiteRecord;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class Suscripciones extends LiteRecord
{
    public function todas($usuarios = [])
    {
        if (!empty($usuarios)) {
            $placeholders = implode(', ', array_fill(0, count($usuarios), '?'));
            $sql = "SELECT * FROM suscripciones WHERE usuarios_idu IN ($placeholders)";
            return self::all($sql, $usuarios);
        }
        return self::all('SELECT * FROM suscripciones');
    }

    public function todasPorUsuario($usuarios_idu)
    {
        return self::all('SELECT * FROM suscripciones WHERE usuarios_idu = ?', [$usuarios_idu]);
    }

    public function suscribirse()
    {
        if (!Session::get('idu')) { http_response_code(401); return; }

        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!$data || !isset($data['endpoint'], $data['keys']['p256dh'], $data['keys']['auth'])) {
            http_response_code(400); return;
        }

        $idu   = Session::get('idu');
        $ua    = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $agent = explode(')', $ua, 2)[0] . ')'; // mismo criterio que ya usas

        // UPSERT por clave única (saltará por suscripcion o por (usuarios_idu,browser))
        $sql = 'INSERT INTO suscripciones
                (usuarios_idu, browser, suscripcion, p256dh, auth, created, updated)
                VALUES
                (?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                usuarios_idu = VALUES(usuarios_idu),
                browser      = VALUES(browser),
                p256dh       = VALUES(p256dh),
                auth         = VALUES(auth),
                updated      = NOW()';

        self::query($sql, [
            $idu,
            $agent,
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
        ]);

        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }

    public function eliminarSuscripcion($id)
    {
        self::query('DELETE FROM suscripciones WHERE id = ? AND usuarios_idu = ?', [$id, Session::get('idu')]);
    }

    public function desuscribirEndpoints($endpoints)
    {
        if (empty($endpoints)) return;
        $placeholders = implode(', ', array_fill(0, count($endpoints), '?'));
        self::query("DELETE FROM suscripciones WHERE suscripcion IN ($placeholders)", $endpoints);
    }

    // Borra por endpoint que llega del cliente (mantiene el resto de dispositivos)
    public function unsuscribirse($data)
    {
        if (!Session::get('idu')) return;

        $data = is_array($data) ? $data : json_decode(file_get_contents('php://input'), true);
        if (empty($data['endpoint'])) return;

        self::query(
            'DELETE FROM suscripciones WHERE usuarios_idu = ? AND suscripcion = ?',
            [Session::get('idu'), $data['endpoint']]
        );
    }

    public function notificar($arr)
    {
        $arr['icon'] = $arr['icon'] ?? '/img/logos/icon-192x192.png';
        $arr['badge'] = $arr['badge'] ?? '/img/logos/badge.png';
        $arr['requireInteraction'] = $arr['requireInteraction'] ?? false;
        $arr['vibrate'] = [100, 200, 100, 100, 75, 25, 100, 200, 100, 500];

        $suscripciones = (empty($arr['usuarios_idu']) || is_array($arr['usuarios_idu']))
            ? $this->todas($arr['usuarios_idu'])
            : $this->todasPorUsuario($arr['usuarios_idu']);

        $notifications = [];
        foreach ($suscripciones as $sus) {
            $url = is_array($arr['url']) ? $arr['url'][$sus->usuarios_idu] : $arr['url'];

            $subscription = Subscription::create([
                'endpoint'  => $sus->suscripcion,
                'publicKey' => $sus->p256dh,
                'authToken' => $sus->auth,
                'keys'      => ['p256dh' => $sus->p256dh, 'auth' => $sus->auth],
            ]);

            $payload = json_encode([
                'title' => $arr['title'],
                'body'  => $arr['body'],
                'icon'  => $arr['icon'],
                'badge' => $arr['badge'],
                'url'   => $url,
                'requireInteraction' => $arr['requireInteraction'],
                'vibrate' => $arr['vibrate'],
            ]);

            $notifications[] = ['subscription' => $subscription, 'payload' => $payload];
        }

        $webPush = new WebPush(Config::get('webpush.auth'));

        foreach ($notifications as $n) {
            $webPush->queueNotification($n['subscription'], $n['payload']);
        }

        $bad = [];
        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess()) {
                $bad[] = $report->getRequest()->getUri()->__toString();
            }
        }
        if ($bad) $this->desuscribirEndpoints($bad);

        return $arr;
    }
}
