<?php
/**
 */
class Conversaciones_usuarios extends LiteRecord
{
	#
	public function validar($usu)
	{
		$usu['conversaciones_idu'] = (string)$usu['conversaciones_idu'];
		$usu['usuarios_idu'] = empty($usu['usuarios_idu'])
			? Session::get('idu')
			: $usu['usuarios_idu'];
		$usu['rol'] = empty($usu['rol']) ? '' : $usu['rol'];
		$usu['creado'] = date('Y-m-d H:i:s');
		return $usu;
	}

	#
	public function patear($conversaciones_idu, $usuarios_idu)
	{
		$sql = 'SELECT * FROM conversaciones_usuarios WHERE conversaciones_idu=? AND usuarios_idu=? AND rol=?';
		$admin = self::first($sql, [$conversaciones_idu, Session::get('idu'), 'admin']);
		if ( ! $admin) {
			return;
		}
		$sql = 'DELETE FROM conversaciones_usuarios WHERE conversaciones_idu=? AND usuarios_idu=?';
		self::query($sql, [$conversaciones_idu, $usuarios_idu]);
	}

	#
	public function crear($usu)
	{
		$usu = $this->validar($usu);
		$sql = 'INSERT INTO conversaciones_usuarios SET conversaciones_idu=?, usuarios_idu=?, rol=?, creado=?';
		$this->query($sql, [$usu['conversaciones_idu'], $usu['usuarios_idu'], $usu['rol'], $usu['creado']]);
		return $usu;
	}

	#
	public function marcarUltimoLeido($conversaciones_idu, $ultimo_leido='')
	{
		$ultimo_leido = empty($ultimo_leido) ? date('Y-m-d H:i:s') : $ultimo_leido;
		$sql = 'UPDATE conversaciones_usuarios SET ultimo_leido=? WHERE conversaciones_idu=? AND usuarios_idu=?';
		self::query($sql, [$ultimo_leido, $conversaciones_idu, Session::get('idu')]);
	}

	#
	public function obtenerUltimoLeido($conversaciones_idu)
	{
		$sql = 'SELECT ultimo_leido FROM conversaciones_usuarios WHERE conversaciones_idu=? AND usuarios_idu=?';
		$conversaciones_usuarios = self::first($sql, [$conversaciones_idu, Session::get('idu')]);
		return empty($conversaciones_usuarios) ? '' : $conversaciones_usuarios->ultimo_leido;
	}

	#
	public function salirse($conversaciones_idu)
	{
		$sql = 'DELETE FROM conversaciones_usuarios WHERE conversaciones_idu=? AND usuarios_idu=?';
		self::query($sql, $conversaciones_idu, Session::get('idu'));
	}

	#
	public function participantes($conversaciones_idu)
	{
		$sql = 'SELECT id FROM conversaciones_usuarios WHERE conversaciones_idu=?';
		$participantes = parent::all($sql, [$conversaciones_idu]);
		return is_array($participantes) ? count($participantes) : 0;
	}

	#
	public function todos($usu)
	{
		if (empty($usu['ultimo_fecha'])) {
			$sql = 'SELECT * FROM conversaciones_usuarios WHERE conversaciones_idu=? ORDER BY creado';
			$usuarios = $this->all($sql, [$usu['conversaciones_idu']]);
			foreach ($usuarios as $usu) {
				if (empty($usu->usuarios_idu)) {
					continue;
				}
				$cargar[$usu->usuarios_idu] = $usu;
			}
			return $cargar;
		}

		$sql = 'SELECT * FROM conversaciones_usuarios WHERE conversaciones_idu=? AND creado>? ORDER BY creado';
		return $this->all($sql, [$usu['conversaciones_idu'], $usu['ultimo_fecha']]);
	}

	#
	public function unirse($usu)
	{
		$usu = $this->validar($usu);
		if ($uno = $this->uno($usu)) {
			#Session::setArray('toast', t('Ya estabas unido.'));
			return (array)$uno;
		}
		$sql = 'INSERT INTO conversaciones_usuarios SET conversaciones_idu=?, usuarios_idu=?, rol=?, creado=?';
		$this->query($sql, [$usu['conversaciones_idu'], $usu['usuarios_idu'], $usu['rol'], $usu['creado']]);
		return $usu;
	}

	#
	public function uno($usu)
	{
		$usu = $this->validar($usu);
		$sql = 'SELECT * FROM conversaciones_usuarios WHERE conversaciones_idu=? AND usuarios_idu=?';
		return $this->first($sql, [$usu['conversaciones_idu'], $usu['usuarios_idu']]);
	}

	# Desarrollando para las conversaciones privadas...
	public function conversacionCon($usuarios_idu)
	{
		$sql = 'SELECT * FROM conversaciones_usuarios WHERE usuarios_idu=? OR usuarios_idu=?';
		return $this->all($sql, [$usuarios_idu, ]);
	}
}
