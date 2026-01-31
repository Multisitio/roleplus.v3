<?php
/**
 */
class Caminos extends LiteRecord
{
	#
	public function guardar($cat)
	{
		if (empty($cat['caminos']['texto'])) {
			return;
		}

		self::eliminar($cat['escenas_idu']);

		foreach ($cat['caminos']['texto'] as $i=>$texto) {
			$keys[] = '(?, ?, ?, ?, ?, ?)';
			$vals[] = Session::get('idu');
			$vals[] = $cat['aventuras_idu'];
			$vals[] = $cat['escenas_idu'];
			$vals[] = _str::uid();
			$vals[] = (string)$texto;
			$vals[] = (string)$cat['caminos']['escena'][$i];
		}

		$sql = 'INSERT INTO caminos (usuarios_idu, aventuras_idu, escenas_idu, idu, texto, escena) VALUES ' . implode(', ', $keys);
		self::query($sql, $vals);
	}

	#
	public function eliminar($escenas_idu)
	{
		$vals[] = Session::get('idu');
		$vals[] = $escenas_idu;

		$sql = 'DELETE FROM caminos WHERE usuarios_idu=? AND escenas_idu=?';
		self::query($sql, $vals);
	}
}
