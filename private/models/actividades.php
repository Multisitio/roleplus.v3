<?php
/**
 */
class Actividades extends LiteRecord
{
	#
	public function validar($reg)
	{
		$reg['fecha_notificacion'] = date('Y-m-d H:i:s');
		return $reg;
	}

	#
	public function actualizar($reg)
	{
		$sql = 'UPDATE actividades SET elemento_nombre=?, aforo=?, fecha_evento=?, fecha_notificacion=? WHERE usuarios_idu=? AND elemento_idu=?';
		$this->query($sql, [$reg['elemento_nombre'], $reg['aforo'], $reg['fecha_evento'], $reg['fecha_evento'], Session::get('idu'), $reg['elemento_idu']]);
	}

	#
	public function crear($reg)
	{
		$reg = $this->validar($reg);
		$sql = 'INSERT INTO actividades SET usuarios_idu=?, tipo=?, elemento_nombre=?, elemento_idu=?, fecha_notificacion=?';
		$this->query($sql, [Session::get('idu'), $reg['tipo'], $reg['elemento_nombre'], $reg['elemento_idu'], $reg['fecha_notificacion']]);
	}

	#
	public function eliminar($reg)
	{
		$sql = 'DELETE FROM actividades WHERE usuarios_idu=? AND elemento_idu=?';
        $this->query($sql, [Session::get('idu'), $reg['elemento_idu']]);
	}

	#
	public function todas()
	{
		$sql = 'SELECT * FROM actividades ORDER BY fecha_notificacion DESC LIMIT 50';
		return $this->all($sql);
	}
}
