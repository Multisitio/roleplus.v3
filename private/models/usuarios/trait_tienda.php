<?php
/**
 */
trait UsuariosTienda
{
    # Tienda
    public function chamanPagado($periocidad='mensual')
    {
        $periocidad = ($periocidad == 'anual') ? 'anual' : 'mensual';
        $protocol = ($_SERVER["SERVER_PROTOCOL"]=='HTTP/1.1') ? 'http://' : 'https://';
        $url = $protocol . $_SERVER['HTTP_HOST'] . "/usuarios/perfil/" . urlencode(Session::get('apodo'));

        if (empty($_SERVER['HTTP_REFERER']) || ! strstr($_SERVER['HTTP_REFERER'], 'paypal')) {
            _mail::send('dj@roleplus.app', 'Una elevación a Chamán no venía de PayPal', '<pre>'.print_r([$url, $_SERVER], 1));
            return Session::setArray('toast', t('Esta elevación la realizará la iA tras comprobación.'));
        }

        $usuario = (new Usuarios)->uno();

        $sql = 'UPDATE usuarios SET rol=5, rol_base=?, ultimo_pago=?, socio=?, cuota=? WHERE idu=?';
        self::query($sql, [$usuario->rol, date('Y-m-d'), date('Y-m-d'), $periocidad, Session::get('idu')]);

        Session::set('rol', 5);
        Session::setArray('toast', t('Elevación conseguida.'));
        _mail::send('dj@roleplus.app', 'Una elevación a Chamán adquirida vía PayPal', '<pre>'.print_r([$url, $_SERVER['HTTP_REFERER']], 1));
    }

    # Tienda
    public function comprarPX($px)
    {
        $protocol = ($_SERVER["SERVER_PROTOCOL"]=='HTTP/1.1') ? 'http://' : 'https://';
        $url = $protocol . $_SERVER['HTTP_HOST'] . "/usuarios/perfil/" . urlencode(Session::get('apodo'));

        # https://www.paypal.com/webapps/hermes?flow=1-P&ulReturn=true
        if (empty($_SERVER['HTTP_REFERER']) or ! strstr($_SERVER['HTTP_REFERER'], 'https://www.paypal.com/') or ! preg_match('/100|500|1000|5000/', $px)) {
            _mail::send('dj@roleplus.app', "Una compra de $px PX debe ser supervisada", '<pre>'.print_r([$url, $_SERVER['HTTP_REFERER']], 1));
            return Session::setArray('toast', t('Esta compra de PX la realizará la iA trás comprobación.'));
        }

        (new Experiencia)->registrar($px, 'Tienda', Session::get('idu'), Session::get('apodo') . ' ' . t('ha comprado PX: ') . $px);

        Session::setArray('toast', "$px " . t('PX han sido agregados a su cuenta.'));
        _mail::send('dj@roleplus.app', "Una compra de $px PX ha sido sumada a un perfil de R+", '<pre>'.print_r([$url, $_SERVER['HTTP_REFERER']], 1));
    }

    # Tienda
    public function comprarRol($rol)
    {
        $uno = $this->uno();

        if ($uno->rol+1 <> $rol) {
            return Session::setArray('toast', t('Solo puede comprar el sigueinte rol.'));
        }

        $precio = 1000*$rol;
        if ($uno->experiencia < $precio) {
            return Session::setArray('toast', t('No tiene los PX suficientes.'));
        }

        $roles = Config::get('roles.singular');

        (new Experiencia)->registrar(-$precio, 'Tienda', Session::get('idu'), Session::get('apodo') . ' ' . t('ha comprado el rol ') . $roles[$rol]);

        $sql = 'UPDATE usuarios SET rol=?, rol_base=? WHERE idu=?';
        self::query($sql, [$uno->rol+1, $uno->rol_base+1, Session::get('idu')]);

        Session::set('rol', $rol);
        Session::setArray('toast', t('Elevación conseguida.'));
        $protocol = ($_SERVER["SERVER_PROTOCOL"]=='HTTP/1.1') ? 'http://' : 'https://';
        $url = $protocol . $_SERVER['HTTP_HOST'] . "/usuarios/perfil/" . urlencode(Session::get('apodo'));
        _mail::send('dj@roleplus.app', 'Elevación comprada con PX', $url);
    }
}
