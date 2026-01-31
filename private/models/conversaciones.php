<?php
/**
 */
class Conversaciones extends LiteRecord
{
    #
    public function arreglo()
    {
        $sql = 'SELECT * FROM conversaciones WHERE creado LIKE ?';
        $conversaciones = self::all($sql, ['2021-%']);
        foreach ($conversaciones as $i=>$con) {
			$sql = 'SELECT c_u.*, usu.apodo, usu.hashtag FROM conversaciones_usuarios c_u, usuarios usu WHERE c_u.usuarios_idu=usu.idu AND c_u.conversaciones_idu=?';
			$con->usuarios = self::all($sql, [$con->idu]);

			$sql = 'SELECT c_m.*, usu.apodo, usu.hashtag FROM conversaciones_mensajes c_m, usuarios usu WHERE c_m.usuarios_idu=usu.idu AND c_m.conversaciones_idu=?';
			$con->mensajes = self::all($sql, [$con->idu]);

			if (count($con->usuarios) < 2 || ! $con->mensajes) {
				$sql = 'DELETE FROM conversaciones WHERE idu=?';
				self::query($sql, [$con->idu]);

				$sql = 'DELETE FROM conversaciones_usuarios WHERE conversaciones_idu=?';
				self::query($sql, [$con->idu]);

				unset($conversaciones[$i]);
				continue;
			}

			if ( ! $con->miembros) {
				$sql = 'UPDATE conversaciones SET miembros=? WHERE idu=?';
				self::query($sql, ["{$con->usuarios[0]->usuarios_idu}, {$con->usuarios[1]->usuarios_idu}", $con->idu]);
			}
        }
        _var::die([count($conversaciones), $conversaciones]);
    }

	#
	public function validar($cha=[])
	{
		$cha['nombre'] = empty($cha['nombre']) ? '' : $cha['nombre'];
		$cha['idu'] = _str::uid();
		$cha['imagen'] = empty($cha['imagen']) ? '' : $cha['imagen'];
		$cha['miembros'] = empty($cha['miembros']) ? '' : $cha['miembros'];
		$cha['creado'] = date('Y-m-d H:i:s');
		$cha['leido'] = date('Y-m-d H:i:s');
		$cha['editado'] = date('Y-m-d H:i:s');
		$cha['borrado'] = date('Y-m-d H:i:s');
		return $cha;
	}

	#
	public function unaConmigo($usuarios_idu)
	{
		$sql = 'SELECT * FROM conversaciones WHERE miembros LIKE ? AND miembros LIKE ? ORDER BY creado DESC';
		return self::first($sql, ['%'.Session::get('idu').'%', "%$usuarios_idu%"]);
	}

	#
	public function crear($usuarios_idu='')
	{
		$conversacion = $this->unaConmigo($usuarios_idu);
		if ($conversacion) {
			return $conversacion->idu;
		}

		$cha = $this->validar();
        $usuario = (new Usuarios)->uno($usuarios_idu);

		$sql = 'INSERT INTO conversaciones SET idu=?, imagen=?, creado=?';
		$values[] = $cha['idu'];
		$values[] = "/img/usuarios/$usuario->idu/$usuario->avatar";
		$values[] = date('Y-m-d H:i:s');

		if ($usuarios_idu) {
			$sql .= ', miembros=?';
			$values[] = Session::get('idu') . ", $usuarios_idu";
		}

		self::query($sql, $values);

		(new Conversaciones_usuarios)->unirse([
			'conversaciones_idu'=>$cha['idu'], 
			'usuarios_idu'=>$usuario->idu, 
		]);

		(new Conversaciones_usuarios)->unirse([
			'conversaciones_idu'=>$cha['idu'], 
			'usuarios_idu'=>Session::get('idu'), 
			'rol'=>'admin', 
		]);

		return $cha['idu'];
    }

	#
	public function idusDeMisConversaciones()
	{
		$sql = 'SELECT con.idu FROM conversaciones con, conversaciones_usuarios c_u WHERE con.idu=c_u.conversaciones_idu AND c_u.usuarios_idu=? AND con.borrado IS NULL';
		return self::all($sql, [Session::get('idu')]);
	}

	#
	public function propagarListados($conversaciones_idu)
	{
		$usuarios = (new Conversaciones_usuarios)->todos(['conversaciones_idu'=>$conversaciones_idu]);

		foreach ($usuarios as $usu) {
			_url::enviarAlCanal('rp_chat_sin_leer_' . $usu->usuarios_idu, [
				'url' => "/registrados/conversaciones/sin_leer",
			]);
		}
	}

	#
	public function registrarUltimo($cha)
	{
		$cha = $this->validar($cha);
		$sql = 'UPDATE conversaciones SET ultimo_mensaje=?, ultimo_usuario=?, ultimo_fecha=? WHERE idu=?';
		$this->query($sql, [$cha['contenido'], Session::get('idu'), $cha['creado'], $cha['conversaciones_idu']]);
	}

	#
	public function todasMisConversaciones()
	{
		$sql = 'SELECT con.* FROM conversaciones con, conversaciones_usuarios c_u WHERE con.idu=c_u.conversaciones_idu AND c_u.usuarios_idu=? AND con.borrado IS NULL ORDER BY con.ultimo_fecha DESC';
		$conversaciones = self::all($sql, [Session::get('idu')]);
		if ( ! $conversaciones) {
			return [];
		}
		$conversaciones_con_usuarios = $this->usuariosEnConversaciones($conversaciones);
		return $conversaciones_con_usuarios;
	}

	#
	public function una($idu)
	{
		$sql = 'SELECT con.* FROM conversaciones con, conversaciones_usuarios c_u WHERE con.idu=c_u.conversaciones_idu AND con.idu=? AND con.borrado IS NULL ORDER BY con.ultimo_fecha DESC';
		$conversaciones = self::all($sql, [$idu]);
		$conversaciones_con_usuarios = $this->usuariosEnConversaciones($conversaciones);
		return array_shift($conversaciones_con_usuarios);
	}

	#
	public function usuariosEnConversaciones($conversaciones)
	{	
		foreach ($conversaciones as $con) {
			$keys[] = '?';
			$values[] = $con->idu;
		}
		$keys = implode(', ', $keys);

		$sql = "SELECT c_u.*, usu.apodo, usu.hashtag, usu.avatar, usu.idu, usu.rol FROM conversaciones_usuarios c_u, usuarios usu WHERE c_u.usuarios_idu=usu.idu AND c_u.conversaciones_idu IN ($keys)";
		$usuarios = self::all($sql, $values);
		foreach ($usuarios as $usu) {
			$usuarios_en_conversaciones[$usu->conversaciones_idu][$usu->usuarios_idu] = $usu;
		}
		foreach ($conversaciones as $con) {
			$con->usuarios = $usuarios_en_conversaciones[$con->idu];
			$conversaciones_con_usuarios[$con->idu] = $con;
		}
		return $conversaciones_con_usuarios;
	}
}
