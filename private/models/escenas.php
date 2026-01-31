<?php
/**
 */
class Escenas extends LiteRecord
{
	#
	public function actualizar($cat)
	{
		$vals[] = Session::get('idu');
		$vals[] = $cat['aventuras_idu'];
		$vals[] = empty($cat['peso']) ? 0 : (string)$cat['peso'];
		$vals[] = empty($cat['nombre']) ? '' : (string)$cat['nombre'];
		$vals[] = (string)$cat['introduccion'];

        $vals[] = empty($_FILES['fotos']['name'][0])
            ? $cat['fotos'][0]
			: _file::saveFiles($_FILES['fotos'], 'img/usuarios/' . Session::get('idu'));

		$vals[] = Session::get('idu');
		$vals[] = $idu = (string)$cat['idu'];

		$sql = 'UPDATE escenas SET usuarios_idu=?, aventuras_idu=?, peso=?, nombre=?, introduccion=?, fotos=? WHERE (usuarios_idu=? OR usuarios_idu IS NULL) AND idu=?';
		self::query($sql, $vals);
		return $idu;
	}

	#
	public function crear($cat)
	{
		$vals[] = Session::get('idu');
		$vals[] = $cat['aventuras_idu'];
		$vals[] = empty($cat['peso']) ? 0 : (string)$cat['peso'];
		$vals[] = empty($cat['nombre']) ? '' : (string)$cat['nombre'];
		$vals[] = $idu = _str::uid($cat['nombre']);
		$vals[] = (string)$cat['introduccion'];

        $vals[] = empty($_FILES['fotos']['name'][0])
            ? $cat['fotos'][0]
			: _file::saveFiles($_FILES['fotos'], 'img/usuarios/' . Session::get('idu'));

		$sql = 'INSERT INTO escenas SET usuarios_idu=?, aventuras_idu=?, peso=?, nombre=?, idu=?, introduccion=?, fotos=?';
		self::query($sql, $vals);
		return $this->una($idu);
	}

	#
	public function eliminar($idu)
	{
		$vals[] = Session::get('idu');
		$vals[] = $idu;

		$sql = 'DELETE FROM escenas WHERE (usuarios_idu=? OR usuarios_idu IS NULL) AND idu=?';
		self::query($sql, $vals);
		Session::delete('escenas_idu');
	}

	#
	public function todas($aventuras_idu)
	{
		$sql = 'SELECT * FROM escenas WHERE aventuras_idu=? ORDER BY peso, nombre';
		return self::all($sql, [$aventuras_idu]);
	}

	#
	public function una($idu='')
	{
		$sql = 'SELECT * FROM escenas WHERE idu=?';
		$una = self::first($sql, [$idu]);
		return $una ? $una : parent::cols();
	}
}
