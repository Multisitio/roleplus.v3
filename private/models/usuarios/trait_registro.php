<?php
/**
 */
trait UsuariosRegistro
{
    # Registro
    public function confirmar($email, $llave)
    {
        $la_clave = base64_decode($llave);

        # CONFIRMA
        $sql = "UPDATE usuarios SET confirmado=1 WHERE email=? AND la_clave=?";
        self::query($sql, [$email, $la_clave]);

        # VERIFICA
        $sql = "SELECT * FROM usuarios WHERE email=? AND la_clave=?";
        $usuario = self::first($sql, [$email, $la_clave]);
        if ($usuario) {
            Session::setArray('toast', t('Usuario confirmado.'));
        }
        else {
            return Session::setArray('toast', t('Usuario no confirmado.'));
        }

        _mail::send('dj@roleplus.app', 'Usuario confirmado en R+', '<pre>'.print_r($usuario, 1));

        Cache::driver()->clean('kumbia.usuarios_totales');

        # ENTRA
        return $this->identificarse(['email'=>$email, 'la_clave'=>$la_clave], 1);
    }

    # Registro
    public function recordar_clave($arr)
    {
        # CAPTCHA
        if ( ! empty($arr['nombre']) or ! empty($arr['apellidos']) ) {
            return Session::setArray('toast', t('¡Atrás Satanás!'));
        }

        $b['email'] = self::validate('email', $arr['email']);
        if ( ! $b['email']) {
            return Session::setArray('toast', t('¿No se deja algo?'));
        }

        $sql = 'SELECT * FROM usuarios WHERE email=?';
        $usuario = self::first($sql, [$b['email']]);

        if ( ! $usuario) {
            return Session::setArray('toast', t('Lo siento, prueba a Crear la cuenta.'));
        }

        # ENVIANDO CORREO
        $protocol = ($_SERVER["SERVER_PROTOCOL"]=='HTTP/1.1') ? 'http://' : 'https://';
        $url = $protocol . $_SERVER['HTTP_HOST'] . "/usuarios/resetear/$usuario->email/" . base64_encode($usuario->la_clave);

        ob_start();
        ?>
<?=t('Ciudadano, ha solicitado el reseteo de su clave de acceso. Siga instrucciones.')?>


<?=t('Pulse el siguiente enlace y su contraseña quedará en blanco. Después deberá introducir una nueva con un mínimo de 12 caracteres.')?>


<?=$url?>


<?=t('Y recuerde, el Ordenador Paranoia, le quiere.')?>
        <?php
        $body = ob_get_clean();

        $to = $b['email'];
        $subject = t('Resetear la clave de R+');

        _mail::sendText($to, $subject, $body);

        Session::setArray('toast', t('Acuda a su cliente de correo.'));

        _mail::send('dj@roleplus.app', 'Usuario reseteando en R+', '<pre>'.print_r([$b['email'], $_SERVER], 1));
    }
    
    # Registro
    public function registrarse($arr, $tipo_de_acceso=0)
    {
        # Captcha
        if ( ! empty($arr['nombre']) or ! empty($arr['apellidos']) )
            return Session::setArray('toast', t('¡Atrás Satanás!'));

        # Los 4 campos necesarios
        if ( empty($arr['email']) or empty($arr['apodo']) or empty($arr['clave']) or empty($arr['terminos']) ) {
            return Session::setArray('toast', t('Algo te estás dejando.'));
        }

        if (strlen($arr['apodo']) < 3) return Session::setArray('toast', t('El apodo ha de tener un mínimo de 3 caracteres.'));

        # Requisitos de la clave
        if (strlen($arr['clave']) < 12) return Session::setArray('toast', t('La contraseña ha de tener un mínimo de 12 caracteres.'));

        # Se evalua el formato del email
        $b['email'] = self::validate('email', $arr['email']);
        if ( ! $b['email']) {
            return Session::setArray('toast', t('El email no es correcto.'));
        }

        # Se comprueba que no exista el email
        $sql = 'SELECT id FROM usuarios WHERE email=? AND confirmado=1';
        $usuario = self::first($sql, [$b['email']]);
        if ($usuario) return Session::setArray('toast', t('Pruebe a recordar su clave, borrar su cuenta o registrarse con otro correo.'));

        $values[] = $b['email'];
        $values[] = $b['idu'] = _str::uid();
        $values[] = $b['token'] = _str::uid();
        $values[] = $b['apodo'] = trim($arr['apodo']);

        # Miramos que el apodo no esté ya confirmado.
        $sql = 'SELECT id FROM usuarios WHERE LOWER(apodo)=? AND confirmado=1';
        $usuario = self::first($sql, [mb_strtolower($b['apodo'])]);
        if ($usuario) {
            return Session::setArray('toast', t('Lo siento, el apodo ya existe.'));
        }

        $b['hashtag'] = _str::hashtag($b['apodo']);
        $sql = 'SELECT id FROM usuarios WHERE hashtag=?';
        $hashtag_exists = self::first($sql, [$b['hashtag']]);
        $values[] = empty($hashtag_exists) ? $b['hashtag'] : $b['idu'];

        $values[] = $b['la_clave'] = password_hash($arr['clave'], PASSWORD_DEFAULT);
        $llave = base64_encode($b['la_clave']);

        $values[] = $b['rol'] = 1;
        $values[] = $b['terminos'] = 1;
        $values[] = $b['tocado'] = date('Y-m-d H:i:s');
        $values[] = $b['ip'] = $_SERVER['REMOTE_ADDR'];
        $values[] = $b['browser'] = $_SERVER['HTTP_USER_AGENT'];

        # Borramos los usuarios con el mismo apodo que no estén confirmados.
        $sql = 'DELETE FROM usuarios WHERE apodo=? AND confirmado=0';
        self::query($sql, [$b['apodo']]);

        $sql = 'INSERT INTO usuarios SET email=?, idu=?, token=?, apodo=?, hashtag=?, la_clave=?, rol=?, terminos=?, tocado=?, ip=?, browser=?';
        self::query($sql, $values);

        # Registrando actividad
        (new Actividades)->crear([
            'usuarios_idu'=>$b['idu'],
            'tipo'=>'usuarios',
            'elemento_nombre'=>$b['apodo'],
            'elemento_idu'=>$b['idu'],
        ]);

        if ($tipo_de_acceso === 2) {
            return self::confirmar($b['email'], $llave);
        }

        # ENVIANDO CORREO
        $protocol = ($_SERVER["SERVER_PROTOCOL"]=='HTTP/1.1') ? 'http://' : 'https://';
        $url = "{$protocol}{$_SERVER['HTTP_HOST']}/usuarios/confirmar/{$b['email']}/$llave";

        ob_start();
        ?>
<?=t('Sigue el camino de baldosas amarillas:')?>


<?=$url?>


<?=t('Desde R+ queremos agradecer la confianza depositada en esta plataforma.')?>


<?=t('Esperamos que este sea el principio de un gran viaje, con muchas alegrías y momentos únicos junto al resto de la comunidad.')?>


<?=t('Gracias y un saludo.')?>
        <?php
        $body = ob_get_clean();

        $to = $b['email'];
        $subject = t('Confirma tu cuenta de correo a R+');

        _mail::sendText($to, $subject, $body);

        Session::setArray('toast', t('Confirme su email en su cliente de correo.'));

        _mail::send('dj@roleplus.app', 'Usuario registrandose en R+', '<pre>'.print_r($b, 1));
    }

    # Registro
    public function resetear($email, $llave)
    {
        $llave = base64_decode($llave);
        if ( ! $llave) {
            return;
        }
        # RESETEA
        $sql = "UPDATE usuarios SET la_clave='', confirmado=1 WHERE email=? AND la_clave=?";
        self::query($sql, [$email, $llave]);

        Session::setArray('toast', t('Llave reseteada, entre con una nueva.'));

        _mail::send('dj@roleplus.app', 'Usuario reseteado en R+', '<pre>'.print_r([$sql, $email, $llave], 1));
    }
}
