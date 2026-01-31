<?php
/**
 */
class Atalaya_conversaciones extends LiteRecord
{
	# 1
	public function todas()
	{
		$sql = 'SELECT * FROM conversaciones ORDER BY ultimo_fecha DESC';
		return parent::all($sql);
	}

	# 2
	public function usuarios()
	{
		$sql = 'SELECT * FROM conversaciones_usuarios ORDER BY creado DESC';
		$usuarios = parent::all($sql);
		return parent::groupBy($usuarios, 'conversaciones_idu');
	}

	# 3
	public function mensajes()
	{
		$sql = 'SELECT * FROM conversaciones_mensajes ORDER BY creado DESC';
		$mensajes = parent::all($sql);
		return parent::groupBy($mensajes, 'conversaciones_idu');
	}

	# 3
	public function eliminar($idu)
	{
		$sql = 'DELETE FROM conversaciones_mensajes WHERE conversaciones_idu=?';
		parent::query($sql, [$idu]);

		$sql = 'DELETE FROM conversaciones_usuarios WHERE conversaciones_idu=?';
		parent::query($sql, [$idu]);

		$sql = 'DELETE FROM conversaciones WHERE idu=?';
		parent::query($sql, [$idu]);
		
		Session::setArray('toast', 'Conversación eliminada');
	}
}