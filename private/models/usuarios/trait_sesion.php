<?php
/**
 */
trait UsuariosSesion
{
	# Sesion
	public function conectados($by='idu')
	{
		$desde_hace = date('Y-m-d H:i:s', strtotime('-10 minutes'));
		$sql = 'SELECT usu.idu, usu.apodo, usu.hashtag, usu.avatar, usu.eslogan, usu.rol FROM usuarios usu WHERE usu.tocado>?';
        $arr = self::all($sql, [$desde_hace]);
        $conectados = [];
        foreach ($arr as $obj) {
            $conectados[$obj->$by] = $obj;
        }
        return $conectados;
	}

    # Sesion
    /*public function entrarSiGalleta()
    {
        if (empty($_COOKIE['llave']['sesion_clave'])) {
            return;
        }
        $sql = "SELECT * FROM usuarios WHERE sesion_clave=? AND confirmado=1 AND terminos=1";
        $usuario = self::first($sql, [$_COOKIE['llave']['sesion_clave']]);
        if ($usuario && $usuario->sesion_valor == $_COOKIE['llave']['sesion_valor']) {
            $this->establecerSesion($usuario);
        }
    }*/

    # Sesion
    public function entrarSiGalleta()
    {
        $llave = $_COOKIE['llave'] ?? null;

        if (
            !is_array($llave) ||
            empty($llave['sesion_clave']) ||
            empty($llave['sesion_valor'])
        ) {
            return;
        }

        $sql = "SELECT * FROM usuarios WHERE sesion_clave=? AND confirmado=1 AND terminos=1";
        $usuario = self::first($sql, [$llave['sesion_clave']]);

        if ($usuario && $usuario->sesion_valor == $llave['sesion_valor']) {
            $this->establecerSesion($usuario);
        }
    }

    # Sesion
    private function establecerSesion(object $usuario) : void
    {
        $usuario = (array)$usuario;

        foreach ($usuario as $key=>$val) {
            if ($key === 'la_clave' ||
                $key === 'sesion_clave' || 
                $key === 'sesion_valor') {
                continue;
            }
            Session::set($key, $val);
        }

        $this->recordarSesion();
    }

    # Sesion
    public function identificarse($arr, $llave=0)
    {
        # CAPTCHA
        if ( ! empty($arr['nombre']) or ! empty($arr['apellidos'])) {
            Session::setArray('toast', t('¡Atrás Satanás!'));
            return false;
        }

        # EMAIL
        $b['email'] = self::validate('email', $arr['email']);
        if ( ! $b['email']) {
            Session::setArray('toast', t('¡Correo no valido!'));
            return false;
        }

        $sql = "SELECT * FROM usuarios WHERE email=? AND confirmado=1";
        $usuario = self::first($sql, [$b['email']]);
        $no_confirmado = t('Usuario no confirmado o credenciales incorrectas. Pruebe a recordar su clave y acceda desde su correo.');
        if ( ! $usuario) {
            Session::setArray('toast', $no_confirmado);
            return false;
        }

        if ($llave === 1) {
            if ($arr['la_clave'] <> $usuario->la_clave) {
                Session::setArray('toast', $no_confirmado);
                return false;
            }
        }
        elseif ($llave === 0) {
            if ($usuario->la_clave && ! password_verify($arr['clave'], $usuario->la_clave)) {
                Session::setArray('toast', $no_confirmado);
                return false;
            }
        }

        $b['tocado'] = date('Y-m-d H:i:s');
        $b['ip'] = $_SERVER['REMOTE_ADDR'];
        $b['browser'] = $_SERVER['HTTP_USER_AGENT'];
        # SI NO HAY CLAVE ES PORQUE SE HA RESETEADO
        if ( ! $usuario->la_clave ) {
            # Requisitos de la clave
            if (strlen($arr['clave']) < 12) {
                Session::setArray('toast', t('La contraseña ha de tener un mínimo de 12 caracteres.'));
                return false;
            }
            $b['la_clave'] = password_hash($arr['clave'], PASSWORD_DEFAULT);

            if ($usuario->rescatado == 1) {
                $b['experiencia'] = $usuario->experiencia + 500;
                $b['rescatado'] = 2;
                Session::setArray('toast', '+500 PX');
            }
            else {
                $b['experiencia'] = $usuario->experiencia;
                $b['rescatado'] = $usuario->rescatado;
            }

            $sql = "UPDATE usuarios SET la_clave=?, experiencia=?, tocado=?, rescatado=?, terminos=?, ip=?, browser=? WHERE id=?";
            $r = self::query($sql, [$b['la_clave'], $b['experiencia'], $b['tocado'], $b['rescatado'], 1, $b['ip'], $b['browser'], $usuario->id]);
        }
        # SE ACTUALIZA LA FECHA DE ACTIVIDAD
        else {
            #$b['la_clave'] = password_hash($arr['clave'], PASSWORD_DEFAULT);
            $sql = "UPDATE usuarios SET tocado=?, terminos=?, ip=?, browser=? WHERE id=?";
            self::query($sql, [$b['tocado'], 1, $b['ip'], $b['browser'], $usuario->id]);
        }

        $arr['rol'] = empty($arr['rol']) ? 0 : $arr['rol'];
        if ($arr['rol'] > $usuario->rol) {
            Session::setArray('toast', t('No tienes permiso.'));
            return false;
        }
        $this->establecerSesion($usuario);
        return $usuario;
    }

    # Sesion
    public function marcarAcceso($contenedor='.usuarios.conectados', $contenido='/usuarios/conectados')
    {
        $sql = 'UPDATE usuarios SET tocado=?, ip=?, browser=? WHERE idu=?';
        self::query($sql, [date('Y-m-d H:i:s'), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], Session::get('idu')]);

        /*_url::enviarAlCanal('conectados', [
            'url' => '/usuarios/conectados',
        ]); ESTO GENERA UN BUCLE INFERNAL */
    }

    # Sesion
    public function recordarSesion()
    {
        $usuario = $this->uno();

        if ($usuario->sesion_clave && $usuario->sesion_valor) {
            $sesion_clave = $usuario->sesion_clave;
            $sesion_valor = $usuario->sesion_valor;
        }
        else {
            $sesion_clave = bin2hex(random_bytes(20));
            $sesion_valor = password_hash($sesion_clave.microtime(true), PASSWORD_DEFAULT);

            $sql = 'UPDATE usuarios SET sesion_clave=?, sesion_valor=? WHERE idu=?';
            self::query($sql, [$sesion_clave, $sesion_valor, Session::get('idu')]);
        }

        $cookie_options = [
            'expires' => strtotime('+100 days'),
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'], 
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict' // None || Lax || Strict
        ];
        $r[] = setcookie('llave[sesion_clave]', $sesion_clave, $cookie_options);   
        $r[] = setcookie('llave[sesion_valor]', $sesion_valor, $cookie_options);

        /*setcookie('llave[sesion_clave]', $sesion_clave, time()+12345678, '/', $_SERVER['HTTP_HOST'], true, true);
        setcookie('llave[sesion_valor]', $sesion_valor, time()+12345678, '/', $_SERVER['HTTP_HOST'], true, true);*/
    }

    # Sesion
    public function salir()
    {
        $sql = "UPDATE usuarios SET sesion_clave='', sesion_valor='' WHERE idu=?";
        self::query($sql, [Session::get('idu')]);

        setcookie('llave', null, -1, '/');
        setcookie('llave[sesion_clave]', null, -1, '/', $_SERVER['HTTP_HOST'], true, true);
        setcookie('llave[sesion_valor]', null, -1, '/', $_SERVER['HTTP_HOST'], true, true);

        #$idioma = Session::get('idioma');
        Session::deleteAll();
        #Session::set('idioma', $idioma);
        #_var::die($_SESSION);
        Session::setArray('toast', t('¡Te saliste!'));
    }
}
