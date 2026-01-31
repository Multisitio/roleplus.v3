<?php
/**
 */
class Ev3_elementos extends LiteRecord
{
	#
	public function salvar($post)
	{
		$vals[] = _str::uid();

		$vals[] = Session::get('idu');

		$vals[] = empty($post['nombre'])
			? 'Sin nombre'
			: trim($post['nombre']);

		$vals[] = trim($post['tipo']);

		if (empty($post['idu'])) {
			$sql = 'INSERT INTO ev3_elementos SET idu=?, usuarios_idu=?, nombre=?, tipo=?';
		}
		else {
			$sql = 'UPDATE ev3_elementos SET nombre=?, tipo=? WHERE usuarios_idu=? AND idu=?';
		}

		self::query($sql, $vals);
	}

	#
	public function eliminar($post_or_idu)
	{
		$aplicar_en = empty($post_or_idu['aplicar_en']) ? 'aventura' : $post_or_idu['aplicar_en'];
		$idu = empty($post_or_idu['idu']) ? $post_or_idu : $post_or_idu['idu'];

		if ($aplicar_en == 'partida' || $aplicar_en == 'ambas') {
			(new Partidas_elementos)->eliminar($idu);
		}
		
		if ($aplicar_en == 'aventura' || $aplicar_en == 'ambas') {
			$sql = 'DELETE FROM elementos WHERE (usuarios_idu=? OR usuarios_idu IS NULL) AND idu=?';
			self::query($sql, [Session::get('idu'), $idu]);
			Session::delete('elementos_idu');
		}
	}

	#
	public function todos($aventuras_idu)
	{
		$sql = 'SELECT * FROM elementos WHERE aventuras_idu=? ORDER BY peso';
		return self::all($sql, [$aventuras_idu]);
	}

	#
	public function uno($idu='')
	{
		$sql = 'SELECT * FROM elementos WHERE idu=?';
		$uno = self::first($sql, [$idu]);
		return $uno ? $uno : parent::cols();
	}
}
