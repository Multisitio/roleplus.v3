<?php
/**
 */
trait UsuariosAdmin
{
    # Admin
    public static function validar($post) 
    {
        foreach ($post as $key=>$val) {
            if ($key == 'apodo') {
                if (strlen($val) < 3) {
                    Session::setArray('toast', t('Mínimo 3 caracteres.'));
                    return false;
                }
                if (strlen($val) > 20) {
                    Session::setArray('toast', t('Máximo 20 caracteres.'));
                    return false;
                }
            }
        }
        return true;
    }

    # Admin
    public function actualizar($campo, $valor='', $test=0)
    {
        if ( ! preg_match('/(avatar|eslogan|fondo_cabecera|fondo_general|fotos|idioma|sobre_mi|ultima_pub)/', $campo) ) {
            Session::setArray('toast', t('¡Atrás Satanás!'));
            return false;
        }

        if ( ! $valor && ! empty($_FILES['imagenes']['name'][0])) {
            $valor = (new Archivos)->incluir($_FILES);
        }

        $sql = "UPDATE usuarios SET $campo=? WHERE idu=?";

        if ($test) _var::die($valor);

        self::query($sql, [$valor, Session::get('idu')]);
        Session::set($campo, $valor);
        return $valor;
    }

    # Admin
    public function eliminar($idu)
    {
        $usuario = self::uno($idu);

        $sql = 'DELETE FROM usuarios WHERE idu=?';
        parent::query($sql, [$idu]);

        (new Actividades)->eliminar(['elemento_idu'=>$idu]);

        Session::setArray('toast', t('Usuario eliminado.'));

        _mail::send('dj@roleplus.app', 'Usuario eliminado en R+', '<pre>'.print_r($usuario, 1));

        Cache::driver()->clean('kumbia.usuarios_totales');
    }

    # Admin
    public function borrarse()
    {
        $usuario = self::uno();

        self::delete($usuario->id);

        (new Actividades)->eliminar(['elemento_idu'=>$usuario->idu]);

        Session::deleteAll();
        Session::setArray('toast', t('Usuario eliminado.'));

        setcookie('llave', null, -1, '/');
        setcookie('llave[sesion_clave]', null, -1, '/', $_SERVER['HTTP_HOST'], true, true);
        setcookie('llave[sesion_valor]', null, -1, '/', $_SERVER['HTTP_HOST'], true, true);

        _mail::send('dj@roleplus.app', 'Usuario eliminado en R+', '<pre>'.print_r($usuario, 1));

        Cache::driver()->clean('kumbia.usuarios_totales');
    }

    # Admin
    public function recalcularAtentos($idu)
    {
        $atentos = (new Acciones)->registrosPorIdu($idu, 'usuarios', 'notificar');

        $sql = 'UPDATE usuarios SET atentos=? WHERE idu=?';
        self::query($sql, [count($atentos), $idu]);
    }

    # Admin
    public function socios()
    {
        $sql = 'SELECT * FROM usuarios WHERE socio IS NOT NULL ORDER BY rol DESC';
        $rows = self::all($sql);
        foreach ($rows as $cols) {
            $socios[$cols->rol][] = $cols;
        }
        return $socios;
    }

    # Admin
    public function paraBoletin() : array
    {
        $usuarios_sin_boletin = (new Configuracion)->usuariosPorClave('no_al_boletin');

        $usuarios = $this->todos([
            'cols' => 'idu, token, email',
            'where' => 'confirmado = 1'
        ]);

        foreach ($usuarios as $usu) {
            if ( ! empty($usuarios_sin_boletin[$usu->idu])) {
                continue;
            }
            $usuarios_con_boletin[] = $usu;
        }

        #_var::die($usuarios_con_boletin);
        return $usuarios_con_boletin ?? [];
    }

    # Admin
    public function todos($arr=[], $values=[])
    {
        $sql = 'SELECT ';
        $sql .=  empty($arr['cols']) 
            ? 'idu,apodo,avatar,seguidores,eslogan,rol,experiencia,socio,sobre_mi,ultima_pub,tocado' 
            : $arr['cols'];
        $sql .= ' FROM usuarios';
        if ( ! empty($arr['where'])) {
            $sql .= ' WHERE ' . $arr['where'];
        }
        $sql .= ' ORDER BY ' . (empty($arr['order']) ? 'apodo' : $arr['order']);
        if ( ! empty($arr['limit'])) {
            $sql .= ' LIMIT ' . $arr['limit'];
        }
        else if ( ! empty($arr['page'])) {
            $sql .= ' LIMIT ' . ((int)$arr['page']-1)*50 . ',75';
        }
        $usuarios = self::all($sql, $values);
        return self::arrayBy($usuarios);
    }

    # Admin
    public function ultimaPagina()
    {
        $sql = "SELECT id FROM usuarios_view";
        $usuarios = self::all($sql);
        return ceil(count($usuarios)/75);
    }

    /*
    select `usuarios`.`id` AS `id`,`usuarios`.`email` AS `email`,`usuarios`.`idu` AS `idu`,`usuarios`.`apodo` AS `apodo`,`usuarios`.`seguidores` AS `seguidores`,`usuarios`.`atentos` AS `atentos`,`usuarios`.`eslogan` AS `eslogan`,`usuarios`.`sobre_mi` AS `sobre_mi`,`usuarios`.`fondo_cabecera` AS `fondo_cabecera`,`usuarios`.`fondo_general` AS `fondo_general`,`usuarios`.`rol` AS `rol`,`usuarios`.`experiencia` AS `experiencia`,`usuarios`.`socio` AS `socio`,`usuarios`.`cuota` AS `cuota`,`usuarios`.`terminos` AS `terminos`,`usuarios`.`ultima_pub` AS `ultima_pub`,`usuarios`.`tocado` AS `tocado`,`usuarios`.`confirmado` AS `confirmado`,`usuarios`.`ip` AS `ip`,`usuarios`.`browser` AS `browser`,`usuarios`.`avatar` AS `avatar` from `usuarios` where `usuarios`.`confirmado` = 1 and `usuarios`.`terminos` = 1 order by `usuarios`.`ultima_pub` desc
    */
    # Admin
    public function ultimosPublicando($pagina=1)
    {
        $sql = "SELECT * FROM usuarios_view LIMIT " . ((int)$pagina-1)*75 . ',75';
        $usuarios = self::all($sql);
        return self::arrayBy($usuarios);
    }

    # Admin
    /*public function uno($usuario='')
    {
        $usuario = ($usuario) ? urldecode($usuario) : Session::get('idu');
        $sql = 'SELECT *, idu as usuarios_idu FROM usuarios WHERE apodo=? OR hashtag=? OR idu=?';
        $uno = self::first($sql, [$usuario, $usuario, $usuario]);
        if (empty($uno->apodo)) {
            $uno = self::cols();
            $uno->apodo = '';
            $uno->experiencia = 0;
            $uno->rol = 0;
        }
        return $uno;
    }*/

    # Admin
    public function uno($usuario='')
    {
        $usuario = $usuario ? urldecode($usuario) : Session::get('idu');

        if ($usuario === null || $usuario === '') {
            $uno = self::cols();
            $uno->apodo       = '';
            $uno->experiencia = 0;
            $uno->rol         = 0;
            return $uno;
        }

        /* 1 · apodo / hashtag */
        $sql = 'SELECT *, idu as usuarios_idu
                FROM   usuarios
                WHERE  apodo = ? OR hashtag = ?
                LIMIT  1';
        $uno = self::first($sql, [$usuario, $usuario]);

        /* 2 · idu (solo si no hubo match) */
        if ( ! $uno) {
            $sql = 'SELECT *, idu as usuarios_idu
                    FROM   usuarios
                    WHERE  idu = ?
                    LIMIT  1';
            $uno = self::first($sql, [$usuario]);
        }

        if (empty($uno->apodo)) {
            $uno = self::cols();
            $uno->apodo       = '';
            $uno->experiencia = 0;
            $uno->rol         = 0;
        }

        return $uno;
    }

    # Admin
    public function unoPorToken($token)
    {
        $sql = 'SELECT * FROM usuarios WHERE token=?';
        return self::first($sql, [$token]);
    }

    # Admin
    /*public function usuariosPorApodos($apodos)
    {
        if ( ! $apodos) {
            return [];
        }

        foreach ($apodos as $apodo) {
            $claves[] = 'apodo=?';
            $values[] = str_replace(['{@', '}'], '', $apodo);
        }

        $sql = 'SELECT apodo, avatar, eslogan, idu, rol
            FROM usuarios
            WHERE ' . implode(' AND ', $claves);

        $usuarios = self::all($sql, $values);

        return self::arrayBy($usuarios);
    }*/

    # Admin
    /* MÉTODO NUEVO POR ChatGPT o3 — CONSULTA ÚNICA CON IN() */
    public function usuariosPorApodos($apodos)
    {
        if (!$apodos) {
            return [];
        }

        $placeholders = array_fill(0, count($apodos), '?');
        $values       = array_map(
            fn($a) => str_replace(['{@', '}'], '', $a),
            $apodos
        );

        $sql = 'SELECT apodo, avatar, eslogan, idu, rol
                FROM usuarios
                WHERE apodo IN (' . implode(',', $placeholders) . ')';

        $usuarios = self::all($sql, $values);

        return self::arrayBy($usuarios);
    }

    # Admin
    public function ofrecerApodos($palabra)
    {
        $palabra = base64_decode($palabra);
        $sql = 'SELECT apodo FROM usuarios WHERE apodo LIKE ? ORDER BY apodo LIMIT 10';
        return self::all($sql, ["%$palabra%"]);
    }
}
