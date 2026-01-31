<?php
/**
 */
class Cronologias_eventos extends LiteRecord
{
	#
	public function actualizar($post)
	{
		$vals[] = $post['cronologias_idu'];
		$vals[] = $post['fecha'];
		$vals[] = $post['titulo'];
		$vals[] = $post['relato'];
		$vals[] = Session::get('idu');
		$vals[] = $post['idu'];

		$sql = 'UPDATE cronologias_eventos SET cronologias_idu=?, fecha=?, titulo=?, relato=? WHERE usuarios_idu=? AND idu=?';
		self::query($sql, $vals);

		Session::setArray('toast', "Evento actualizado.");
	}

	#
	public function crear($post)
	{
		if ( ! (new Cronologias)->una($post['cronologias_idu'])) {
			return Session::setArray('toast', "No es tu cronología.");
		}

		$vals[] = _str::uid();
		$vals[] = $post['cronologias_idu'];
		$vals[] = Session::get('idu');
		$vals[] = $post['fecha'];
		$vals[] = $post['titulo'];
		$vals[] = $post['relato'];

		$sql = 'INSERT INTO cronologias_eventos SET idu=?, cronologias_idu=?, usuarios_idu=?, fecha=?, titulo=?, relato=?';
		self::query($sql, $vals);

		Session::setArray('toast', "Evento creado.");
	}

	#
	public function eliminar($post)
	{
		$vals[] = Session::get('idu');
		$vals[] = $post['idu'];

		$sql = 'DELETE FROM cronologias_eventos WHERE usuarios_idu=? AND idu=?';
		self::query($sql, $vals);

		Session::setArray('toast', "Evento eliminado.");
	}

	#
	public function todos($cronologia='')
	{
		$vals[] = Session::get('idu');

		$sql = 'SELECT * FROM cronologias_eventos WHERE usuarios_idu=?';
		if ($cronologia) {
			$vals[] = $cronologia;
			$sql .= ' AND cronologias_idu=?';
		}
		$sql .= ' ORDER BY fecha';

		$eventos = self::all($sql, $vals);
		return parent::groupBy($eventos, 'cronologias_idu');
	}

	# 
	public function uno($idu)
	{
		$vals[] = $idu;

		$sql = 'SELECT * FROM cronologias_eventos WHERE idu=?';
		return self::first($sql, $vals) ?: parent::cols();
	}
}
