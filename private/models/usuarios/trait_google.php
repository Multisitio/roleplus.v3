<?php
use Google\Client;
/**
 */
trait UsuariosGoogle
{
    # Google
    public function identificarseConGoogle()
    {
        // El SDK solo es necesario al verificar credenciales de Google.
        require_once VENDOR_PATH . 'autoload.php';

        #_var::die($_POST);
        $keys = Config::get('keys.google.oauth2');
        $keys = json_decode($keys, true)['web'];

        $credentialToken = $_POST['credential'];
        #$csrfToken = $_POST['g_csrf_token'];

        #$client = new Google_Client(['client_id' => $keys['client_id']]);
        #$payload = $client->verifyIdToken($keys['client_id']);

        $client = new Google\Client();
        $client->setClientId($keys['client_id']);
        $client->setClientSecret($keys['client_secret']);
        $payload = $client->verifyIdToken($credentialToken);
        if ($payload) {
            $arr['email'] = $payload['email'];
            _mail::send('dj@roleplus.app', 'Usuario identificandose con Google', '<pre>'.print_r($arr, 1));
            return self::identificarse($arr, 2);
        }
        return Session::setArray('toast', t('¡Credenciales de Google inválidas!'));
    }

    # Google
    public function registrarseConGoogle()
    {
        // El SDK solo es necesario al verificar credenciales de Google.
        require_once VENDOR_PATH . 'autoload.php';

        #_var::die($_POST);
        $keys = Config::get('keys.google.oauth2');
        $keys = json_decode($keys, true)['web'];

        $credentialToken = $_POST['credential'];
        #$csrfToken = $_POST['g_csrf_token'];

        #$client = new Google_Client(['client_id' => $keys['client_id']]);
        #$payload = $client->verifyIdToken($keys['client_id']);

        $client = new Google\Client();
        $client->setClientId($keys['client_id']);
        $client->setClientSecret($keys['client_secret']);
        $payload = $client->verifyIdToken($credentialToken);
        if ($payload) {
            $arr['email'] = $payload['email'];
            $arr['apodo'] = $payload['given_name'];
            $arr['clave'] = _str::uid();
            $arr['terminos'] = 1;
            #_var::die($arr);
            _mail::send('dj@roleplus.app', 'Usuario registrandose con Google', '<pre>'.print_r($arr, 1));
            return self::registrarse($arr, 2);
        }
        return Session::setArray('toast', t('¡Credenciales de Google inválidas!'));
    }
}
