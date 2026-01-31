<?php
/**
 */
class Eventos extends LiteRecord
{
    #
	public function actualizar($reg)
	{
        $reg = $this->validar($reg);
        if ( ! $reg) return;

        $sql = "UPDATE eventos SET aventuras_idu=?, tipo=?, nombre=?, fotos=?, organiza=?, donde=?, dia_desde=?, hora_desde=?, dia_hasta=?, hora_hasta=?, aforo=?, notas=? WHERE usuarios_idu=? AND idu=?";

        #_var::die([$sql, $reg['tipo'], $reg['nombre'], $reg['fotos'], $reg['organiza'], $reg['donde'], $reg['dia_desde'], $reg['hora_desde'], $reg['dia_hasta'], $reg['hora_hasta'], $reg['aforo'], $reg['notas'], Session::get('idu'), $reg['idu']]);

        self::query($sql, [$reg['aventuras_idu'], $reg['tipo'], $reg['nombre'], $reg['fotos'], $reg['organiza'], $reg['donde'], $reg['dia_desde'], $reg['hora_desde'], $reg['dia_hasta'], $reg['hora_hasta'], $reg['aforo'], $reg['notas'], Session::get('idu'), $reg['idu']]);

        Session::setArray('toast', t('Elemento actualizado.'));

        # Registrando actividad
        if (preg_match('/charlas|jornadas|partidas/', $reg['tipo'])) {
            (new Actividades)->actualizar([
                'elemento_nombre'=>$reg['nombre'],
                'elemento_idu'=>$reg['idu'],
                'aforo'=>$reg['aforo'],
                'fecha_evento'=>$reg['dia_desde'],
                'fecha_notificacion'=>$reg['dia_desde'],
            ]);
        }
		return $reg['idu'];
    }

    #
    public function apuntados($publicaciones)
    {
		if ( ! $publicaciones) {
			return [];
		}
		$keys = $vals = [];
		foreach ($publicaciones as $pub) {
            $keys[] = '?';
            $vals[] = $pub->idu;
		}
		$keys = implode(', ', $keys);

        $sql = 'SELECT e_u.*, usu.apodo, usu.hashtag, usu.avatar, usu.email, usu.eslogan FROM eventos_usuarios e_u, usuarios usu WHERE e_u.usuarios_idu=usu.idu AND e_u.reserva=0';
		if ($keys) {
 			$sql .= " AND eventos_idu IN ($keys)";
		}
        $arr = self::all($sql, $vals);
        $apuntados = [];
        foreach ($arr as $obj) {
            $apuntados[$obj->eventos_idu][$obj->usuarios_idu] = $obj;
        }
        return $apuntados;
    }

    #
    public function apuntadosEnReserva($publicaciones)
    {
		if ( ! $publicaciones) {
			return [];
		}
		$keys = $vals = [];
		foreach ($publicaciones as $pub) {
            $keys[] = '?';
            $vals[] = $pub->idu;
		}
		$keys = implode(', ', $keys);

        $sql = 'SELECT e_u.*, usu.apodo, usu.hashtag, usu.avatar, usu.email, usu.eslogan FROM eventos_usuarios e_u, usuarios usu WHERE e_u.usuarios_idu=usu.idu AND e_u.reserva=1';
		if ($keys) {
 			$sql .= " AND eventos_idu IN ($keys)";
		}
        $arr = self::all($sql, $vals);
        $apuntados = [];
        foreach ($arr as $obj) {
            $apuntados[$obj->eventos_idu][$obj->usuarios_idu] = $obj;
        }
        return $apuntados;
    }

    #
    public function apuntarse($publicaciones_idu)
    {
        $publicacion = (new Publicaciones)->una($publicaciones_idu);
        if (date('Y-m-d H:i:s') > $publicacion->evento_desde) {
            Session::setArray('toast', t('Lo siento, las inscripciones están cerradas.'));
            return $publicacion;
        }
        $apuntado = $this->comprobarSiApuntado($publicaciones_idu);
        if ($apuntado) {
            Session::setArray('toast', t('Ya estabas apuntado.'));
            return $publicacion;
        }
        $apuntados = $this->apuntados([$publicacion->idu => $publicacion]);
        $n_apuntados = empty($apuntados[$publicaciones_idu])
            ? 0
            : count($apuntados[$publicaciones_idu]);
        $reserva = ($n_apuntados < $publicacion->evento_aforo) ? 0 : 1;
		$sql = 'INSERT INTO eventos_usuarios SET usuarios_idu=?, eventos_idu=?, fecha_apuntado=?, reserva=?';
        self::query($sql, [Session::get('idu'), $publicaciones_idu, date('Y-m-d H:i:s'), $reserva]);
        Session::setArray('toast', t('Apuntado.'));

        (new Suscripciones)->notificar([
            'usuarios_idu'=>$publicacion->usuarios_idu,
            'title'=>$publicacion->titulo,
            'body'=>t('Me apunto al evento.') . "\n",
            'icon'=>'/img/usuarios/' . Session::get('idu') . '/xxs.' . Session::get('avatar'),
            'url'=>"/usuarios/perfil/" . urlencode($publicacion->apodo) . "/publicacion/$publicacion->idu/"
        ]);
        return $publicacion;
    }

    #
    public function comprobarSiApuntado($eventos_idu)
    {
		$sql = 'SELECT * FROM eventos_usuarios WHERE usuarios_idu=? AND eventos_idu=?';
        return Eventos::first($sql, [Session::get('idu'), $eventos_idu]);
    }

    #
	public function crear($tipo, $reg)
	{
        $nombre = trim($reg['nombre']);

        if (strlen($nombre) < 3) {
            return Session::setArray('toast', t('El evento ha de tener un mínimo de 3 caracteres.'));
        }

		$sql = 'SELECT * FROM eventos WHERE usuarios_idu=? AND tipo=? AND nombre=?';
        $evento = self::first($sql, [Session::get('idu'), $tipo, $nombre]);
        if ($evento) {
            return Session::setArray('toast', t('Lo siento, ese nombre está pillado.'));
        }

        $values[] = $usuarios_idu = Session::get('idu');
        $values[] = empty($reg['aventuras_idu']) ? '' : $reg['aventuras_idu'];
        $values[] = $tipo;
        $values[] = $nombre;
		$values[] = $idu = _str::uid($nombre);

        $reg = $this->validar($reg);
        $values[] = $reg['fotos'];
        $values[] = $reg['organiza'];
        $values[] = $reg['donde'];
        $values[] = $reg['dia_desde'];
        $values[] = $reg['hora_desde'];
        $values[] = $reg['dia_hasta'];
        $values[] = $reg['hora_hasta'];
        $values[] = $reg['aforo'];
        $values[] = $reg['notas'];

		$sql = "INSERT INTO eventos SET usuarios_idu=?, aventuras_idu=?, tipo=?, nombre=?, idu=?, fotos=?, organiza=?, donde=?, dia_desde=?, hora_desde=?, dia_hasta=?, hora_hasta=?, aforo=?, notas=?";
        self::query($sql, $values);
        Session::setArray('toast', t('Evento creado.'));

        # Registrando actividad
        if ($tipo=='charlas' or $tipo=='jornadas' or $tipo=='partidas') {
            (new Actividades)->crear([
                'tipo'=>$tipo,
                'elemento_nombre'=>$nombre,
                'elemento_idu'=>$idu,
            ]);
        }

        if ($tipo=='charla') {
            $contenido = t('Apúntate a esta charla.');
        }
        elseif ($tipo=='jornadas') {
            $contenido = t('Apúntate a estas jornadas.');
        }
        elseif ($tipo=='partidas') {
            $contenido = t('Apúntate a esta partida.');
        }
        else {
            $contenido = t('Apúntate a este evento.');
        }

        if ( ! preg_match('/localhost|roleplus\.vh/', $_SERVER['HTTP_HOST']) and ! $reg['sin_notis']) {
            (new Suscripciones)->notificar([
                'title'=>$nombre,
                'body'=>"$contenido\n",
                'icon'=>"/img/usuarios/$usuarios_idu/xxs." . Session::get('avatar'),
                'url'=>"/eventos/ver/$tipo/$idu/"
            ]);
        }
		return $idu;
	}

    #
    public function desapuntarse($publicaciones_idu)
    {
        $publicacion = (new Publicaciones)->una($publicaciones_idu);
        $apuntado = $this->comprobarSiApuntado($publicaciones_idu);
        if ( ! $apuntado) {
            return $publicacion;
        }
		$sql = 'DELETE FROM eventos_usuarios WHERE usuarios_idu=? AND eventos_idu=?';
        self::query($sql, [Session::get('idu'), $publicaciones_idu]);
        Session::setArray('toast', t('Desapuntado.'));

        $apuntados = $this->apuntados([$publicacion]);
        $reservas = $this->apuntadosEnReserva([$publicacion]);
        if (count($apuntados[$publicacion->idu]) < $publicacion->evento_aforo and  $reservas) {
            shuffle($reservas[$publicaciones_idu]);
            $usuarios_idu = array_shift($reservas[$publicaciones_idu])->usuarios_idu;
            $sql = 'UPDATE eventos_usuarios SET reserva=0 WHERE usuarios_idu=? AND eventos_idu=?';
            self::query($sql, [$usuarios_idu, $publicaciones_idu]);
        }
        return $publicacion;
    }

    #
	public function eliminar($reg)
	{
		$sql = 'DELETE FROM eventos WHERE usuarios_idu=? AND idu=?';
        self::query($sql, [Session::get('idu'), $reg['idu']]);
        Session::setArray('toast', t('Evento eliminado.'));

        # Eliminando actividad
        if ($reg['tipo']=='jornadas' or $reg['tipo']=='partidas') {
            (new Actividades)->eliminar(['elemento_idu'=>$reg['idu']]);
        }
    }

    #
    public function notificar($eventos_idu='')
    {
        if (Session::get('apodo') <> 'Mr demonio') {
            return;
        }

        $evento = $this->uno($eventos_idu);

        if ( ! $evento) {
            return;
        }

        if ($evento->tipo=='jornadas') {
            $contenido = t('Jornada de rol.');
        }
        elseif ($evento->tipo=='partidas') {
            $contenido = t('Partida de rol.');
        }
        else {
            $contenido = t('Evento.');
        }

        (new Suscripciones)->notificar([
            'title'=>$evento->nombre,
            'body'=>"$contenido\n",
            'icon'=>'/img/usuarios/' . Session::get('idu') . '/xxs.' . Session::get('avatar'),
            'url'=>"/eventos/ver/$evento->tipo/$evento->idu"
        ]);
    }

    #
    public function partidaPorAventura($aventuras_idu='')
    {
		$sql = 'SELECT * FROM eventos WHERE aventuras_idu=?';
        $uno = self::first($sql, [$aventuras_idu]);
        return empty($uno) ? parent::cols() : $uno;
    }

	#
	public function rolesDeJugador()
	{
		return ['jugador'=>'Jugador', 'master'=>'Máster'];
	}

    #
    public function todasMisPartidas()
    {
        $sql = 'SELECT * FROM eventos
            WHERE usuarios_idu=? AND tipo=?
            ORDER BY dia_desde DESC, hora_desde DESC
            LIMIT 10';
        return self::all($sql, [Session::get('idu'), 'partidas']);
    }

    #
    public function todos($tipo)
    {
        $sql = 'SELECT * FROM eventos
            WHERE tipo=?
            ORDER BY dia_desde DESC, hora_desde DESC
            LIMIT 25';
        return self::all($sql, [$tipo]);
    }

    #
    public function uno($idu='')
    {
		$sql = 'SELECT * FROM eventos WHERE idu=?';
        $uno = self::first($sql, [$idu]);
        return empty($uno) ? parent::cols() : $uno;
    }

	#
	public function validar($reg)
	{
        $reg['aventuras_idu'] = empty($reg['aventuras_idu']) ? '' : $reg['aventuras_idu'];

        $reg['tipo'] = empty($reg['tipo']) ? 'partidas' : $reg['tipo'];

        $reg['nombre'] = empty($reg['nombre']) ? t('Pon un nombre') : $reg['nombre'];

        $reg['fotos'] = empty($reg['fotos']) ? '' : $reg['fotos'];
        $reg['fotos'] = empty($_FILES['imagenes']['name'][0])
            ? $reg['fotos']
            : (new Archivos)->incluir($_FILES);

        if (empty($reg['organiza'])) {
            $usuario = (new Usuarios)->uno(Session::get('idu'));
            $reg['organiza'] = empty($reg['organiza']) ? $usuario->apodo : $reg['organiza'];
        }

        $reg['donde'] = empty($reg['donde']) ? '' : $reg['donde'];

        $reg['aforo'] = empty($reg['aforo']) ? 3 : $reg['aforo'];

        $reg['dia_desde'] = empty($reg['dia_desde']) ? date('Y-m-d') : $reg['dia_desde'];

        $reg['hora_desde'] = empty($reg['hora_desde']) ? date('H:i:s') : $reg['hora_desde'];

        if (empty($reg['dia_hasta']) or $reg['dia_hasta'] < $reg['dia_desde']) {
            $reg['dia_hasta'] = date('Y-m-d', strtotime('+3 hours', strtotime("{$reg['dia_desde']} {$reg['hora_desde']}")));
        }

        if (empty($reg['hora_hasta']) or ($reg['dia_desde'] == $reg['dia_hasta'] and $reg['hora_hasta'] < $reg['hora_desde'])) {
            $reg['hora_hasta'] = date('H:I:s', strtotime('+3 hours', strtotime("{$reg['dia_desde']} {$reg['hora_desde']}")));
        }

        $reg['sin_notis'] = empty($reg['sin_notis']) ? 0 : 1;

        return $reg;
    }
}
