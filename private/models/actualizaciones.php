<?php
/**
 */
class Actualizaciones extends LiteRecord
{
	# Actualizaciones no dirigidas o dirigidas a un solo usuario
	public function cargar($dat)
	{
		# Por defecto no se adjunta el contenido
		$dat['adjuntar'] = empty($dat['adjuntar']) ? 0 : 1;
		# La fecha por defecto es la actual
		$dat['fecha'] = empty($dat['fecha']) ? date('Y-m-d H:i:s') : $dat['fecha'];
		# Actualizacion no dirigida por defecto
		$dat['usuarios_idu'] = empty($dat['usuarios_idu']) ? '' : $dat['usuarios_idu'];

		$sql1 = 'SELECT id FROM actualizaciones WHERE (usuarios_idu IS NULL OR usuarios_idu="" OR usuarios_idu=?) AND contenido=?';
		$hay = self::first($sql1, [Session::get('idu'), $dat['contenido']]);
		if ($hay) {
			$this->borrar($hay->id);
		}

		$sql2 = 'INSERT INTO actualizaciones SET usuarios_idu=?, contenido=?, adjuntar=?, contenedor=?, fecha=?';
		$res = self::query($sql2, [$dat['usuarios_idu'], $dat['contenido'], $dat['adjuntar'], $dat['contenedor'], $dat['fecha']]);

		$this->borrarCaducados();

		if ( ! empty($dat['debug'])) {
			_var::die([$sql1, $hay, $sql2, $dat]);
		}
	}

	#
	public function cargarUsuarios($dat=[], $conectados=[])
	{
		if (empty($conectados)) {
			$conectados = (new Usuarios)->conectados();
		}

		/*if ($conectados and empty($dat['usuarios'])) {
			foreach ($conectados as $idu=>$obj) {
				$dat['usuarios'][] = $idu;
			}
		}*/

		$sql = "INSERT INTO actualizaciones (usuarios_idu, contenido, adjuntar, contenedor, fecha) VALUES";

		$values = [];
		$query = 0;
		#_var::die($dat);
		foreach ($dat['usuarios'] as $usuarios_idu=>$obj) {
			if (empty($conectados[$usuarios_idu])) {
				continue;
			}
            $sql .= " (?, ?, ?, ?, ?),";
            $values[] = $usuarios_idu;
            $values[] = is_array($dat['contenido'])
				? $dat['contenido'][$usuarios_idu]
				: $dat['contenido'];
            $values[] = $dat['adjuntar'];
            $values[] = $dat['contenedor'];
			$values[] = $dat['fecha'];
			$query = 1;
		}
		if ($query) {
			$sql = rtrim($sql, ',');
			self::query($sql, $values);
		}

		$this->borrarCaducados();

		if ( ! empty($dat['debug'])) {
			_var::die([$dat, $conectados, $sql, $values]);
		}
	}

	#
	public function leer($fecha='', $usuario_idu='')
	{
		$sql = 'SELECT * FROM actualizaciones WHERE (usuarios_idu=?';
		$values1[] = '';

		if ($fecha) {
			$sql .= ' AND fecha>?)';
			$values1[] = date('Y-m-d H:i:s', $fecha);
		}
		else {
			$sql .= ')';
		}
		$sql .= ' OR (usuarios_idu=?) ORDER BY fecha DESC';
		$values1[] = $usuario_idu;

		$actualizaciones = self::all($sql, $values1);

		if ($actualizaciones) {
			$sql2 = 'DELETE FROM actualizaciones WHERE usuarios_idu=?';
			$values2[] = $usuario_idu;
			self::query($sql2, $values2);

			return $actualizaciones;
		}
	}

	# Esto parece útil
	public function borrar($id)
	{
		$sql = 'DELETE FROM actualizaciones WHERE id=?';
		self::query($sql, [$id]);
	}

	# No sé si esto es realmente necesario
	public function borrarCaducados()
	{
		$fecha = date('Y-m-d H:i:s', strtotime('-2 minutes'));
		$sql = 'DELETE FROM actualizaciones WHERE fecha<?';
		self::query($sql, [$fecha]);
	}
}
