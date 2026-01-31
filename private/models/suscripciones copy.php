<?php
require_once VENDOR_PATH . 'autoload.php';
use Kumbia\ActiveRecord\LiteRecord;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
/**
 */
class Suscripciones extends LiteRecord
{
    # Grupo de usuarios o todos si no se pasa una matriz con los idu de los usuarios
    public function todas($usuarios=[])
    {
        if ($usuarios) {
            foreach ($usuarios as $usuarios_idu) {
                $keys[] = '?';
                $vals[] = $usuarios_idu;
            }
            $keys = implode(', ', $keys);
            $sql = "SELECT * FROM suscripciones WHERE usuarios_idu IN ($keys)";
            return self::all($sql, $vals);
        }
        $sql = 'SELECT * FROM suscripciones';
        return self::all($sql);
    }

    #
    public function todasPorUsuario($usuarios_idu)
    {
        $sql = 'SELECT * FROM suscripciones WHERE usuarios_idu=?';
        return self::all($sql, [$usuarios_idu]);
    }

    #
    public function suscribirse()
    {
        if ( ! Session::get('idu')) {
            return Session::setArray('toast', t('Su identificador está vacio, revise que no esté bloqueando desde su navegador la cookie que guarda la sesión.'));
        }

        $a = json_decode(file_get_contents('php://input'), true);

        $agent = explode(')', $_SERVER['HTTP_USER_AGENT'], 2)[0] . ')';

        $sql = 'SELECT id FROM suscripciones WHERE browser=?';
        
        $o = self::first($sql, [$agent]);
        if ($o) {
            return;
        }

        $sql = 'INSERT INTO suscripciones SET usuarios_idu=?, browser=?, suscripcion=?, p256dh=?, auth=?';

        self::query($sql, [Session::get('idu'), $agent, $a['endpoint'], $a['keys']['p256dh'], $a['keys']['auth']]);

        Session::setArray('toast', t('Alta en notificaciones.'));
    }

    #
    public function eliminarSuscripcion($id)
    {
        $sql = 'DELETE FROM suscripciones WHERE id=? AND usuarios_idu=?';
        self::query($sql, [$id, Session::get('idu')]);
    }

    #
    public function desuscribirEndpoints($endpoints)
    {
        _mail::send('ia@roleplus.app', 'Suscripciones::desuscribirEndpoints', '<pre>'.print_r($endpoints, 1));
        foreach ($endpoints as $endpoint) {
            $in[] = '?';
        }
        $in = implode(', ', $in);
        if ( ! $in) {
            return;
        }
        $sql = "DELETE FROM suscripciones WHERE suscripcion IN ($in)";

        self::query($sql, $endpoints);
    }

    #
    public function unsuscribirse($suscription)
    {
        $agent = explode(')', $_SERVER['HTTP_USER_AGENT'], 2)[0] . ')';

        $sql = 'DELETE FROM suscripciones WHERE usuarios_idu=? AND browser=?';
        self::query($sql, [Session::get('idu'), $agent]);
        Session::setArray('toast', t('Baja en notificaciones.'));
    }

    /*
    curl -X POST --header "Authorization: key=AIzaSyAPqLjGWW8tThJ8Ye14k_Rb6uVRlBvsIXQ" \
    --Header "Content-Type: application/json" \
    https://fcm.googleapis.com/fcm/send \
    -d "{\"to\":\"cecnrMlGFbc:APA91bF3JYd01sQ42kRwHIzWjsgy3FUdCwgmGxbv5EMQi15Wr0vtIUXeu1w8AFa-RXx4Wv8bpoJW-4AA8DUnekCuii3roziTthyDl3VKCVY3EBr42XXPTIUpTrCHuEtKPAGHExS7-GFR\",\"notification\":{\"body\":\"ENTER YOUR MESSAGE HERE\"}}"
    */
    public function notificar($arr)
    {
        #Logger::debug($arr);
        #return $arr;
        # En local no funcionan las notis
        if (preg_match('/localhost|roleplus\.vh/', $_SERVER['HTTP_HOST'])) {
            return $arr;
        }
        $arr['icon'] = empty($arr['icon']) ? '/img/icon-192x192.png' : $arr['icon'];
        $arr['image'] = empty($arr['image']) ? '/img/logos/badge.png' : $arr['image'];
        $arr['requireInteraction'] = empty($arr['requireInteraction']) ? false : $arr['requireInteraction'];        
        # Marcha Imperial de Star Wars (tema de Darth Vader)
        #$arr['vibrate'] = [500,110,500,110,450,110,200,110,170,40,450,110,200,110,170,40,500];
        # Afeitado y corte de pelo
        $arr['vibrate'] = [100,200,100,100,75,25,100,200,100,500,100,200,100,500];

        # Envío de un payload a los usuarios previstos
        $suscripciones = (empty($arr['usuarios_idu']) or is_array($arr['usuarios_idu']))
            # Para todos si está vacio o un grupo si es un array
            ? $this->todas($arr['usuarios_idu'])
            # Para un usuario si es 
            : $this->todasPorUsuario($arr['usuarios_idu']);

        # Aquí se montan las notificaciones con los datos de cada suscripción y el payload
        $notifications = [];
        foreach ($suscripciones as $sus)
        {
            #$url = 'https://' . $_SERVER['HTTP_HOST']; 
            $url = is_array($arr['url']) ? $arr['url'][$sus->usuarios_idu] : $arr['url'];
            #_mail::send('ia@roleplus.app', 'URL en notificación', print_r([$url], 1));

            $notifications[] = [
                'subscription' => Subscription::create([
                    "endpoint" => $sus->suscripcion,
                    'publicKey' => $sus->p256dh,
                    'authToken' => $sus->auth,
                    "keys" => [
                        'p256dh' => $sus->p256dh,
                        'auth' => $sus->auth
                    ],
                ]),
                'payload' => (new Payload)
                    ->body(_html::hd($arr['body']))
                    ->icon($arr['icon'])
                    ->image($arr['image'])
                    ->requireInteraction($arr['requireInteraction'])
                    ->title($arr['title'])
                    ->url($url)
                    ->vibrate($arr['vibrate'])
                    ->send()
            ];
        }

        $webPush = new WebPush(Config::get('webpush.auth'));

        # Aquí se envían las notificación montadas
        foreach ($notifications as $notification) {
            $webPush->queueNotification(
                $notification['subscription'],
                $notification['payload']
            );
        }

        # Sí la notificación falla se prepara un informe
        $mail_and_unsub = 0;
        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();

            if ( ! $report->isSuccess()) {      
                $requestToPushService = $report->getRequest();
                $responseOfPushService = $report->getResponse();
                $failReason = $report->getReason();
                $isTheEndpointWrongOrExpired = $report->isSubscriptionExpired();

                $messages[] = "[x] Message failed to sent for subscription {$endpoint}: {$report->getReason()}";

                $endpoints[] = $endpoint;

                $mail_and_unsub = 1;
            }
        }

        # Se envía el informe preparado por correo
        if ($mail_and_unsub) {
            _mail::send('ia@roleplus.app', 'Message failed to sent for subscription', '<pre>'.print_r([$requestToPushService, $responseOfPushService, $failReason, $isTheEndpointWrongOrExpired, $messages, $endpoints, $_SESSION, $_SERVER], 1));

            $this->desuscribirEndpoints($endpoints);
            unset($messages, $endpoints);
        }

        return $arr;
    }
}
