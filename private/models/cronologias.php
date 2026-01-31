<?php
/**
 */
class Cronologias extends LiteRecord
{
	#
	public function actualizar($post)
	{
		$vals[] = $post['titulo'];
		$vals[] = $post['idu'];
		$vals[] = Session::get('idu');

		$sql = 'UPDATE cronologias SET titulo=? WHERE idu=? AND usuarios_idu=?';
		self::query($sql, $vals);

		Session::setArray('toast', "Cronología actualizada.");
	}

	#
	public function crear($post)
	{
		$vals[] = _str::uid();
		$vals[] = Session::get('idu');
		$vals[] = $post['titulo'];

		$sql = 'INSERT INTO cronologias SET idu=?, usuarios_idu=?, titulo=?';
		self::query($sql, $vals);

		Session::setArray('toast', "Cronología creada.");
	}

	#
	public function eliminar($idu)
	{
		$sql = 'DELETE FROM cronologias WHERE usuarios_idu=? AND idu=?';
		self::query($sql, [Session::get('idu'), $idu]);

		$sql = 'DELETE FROM cronologias_eventos WHERE usuarios_idu=? AND cronologias_idu=?';
		self::query($sql, [Session::get('idu'), $idu]);

		Session::setArray('toast', "Cronología eliminada.");
	}

	#
	public function todas($usuarios_idu='')
	{
		if ($usuarios_idu) {
			$sql = 'SELECT * FROM cronologias WHERE usuarios_idu=?';
			return self::all($sql, [$usuarios_idu]);
		}
		$sql = 'SELECT * FROM cronologias';
		return self::all($sql);
	}

	# 
	public function una($idu)
	{
		$vals[] = $idu;

		$sql = 'SELECT * FROM cronologias WHERE idu=?';
		return self::first($sql, $vals) ?: parent::cols();
	}
}
