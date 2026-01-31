<?php
/**
 */
class Aventuras extends LiteRecord
{
	#
	public function actualizar($cat)
	{
		$vals[] = Session::get('idu');
		$vals[] = empty($cat['nombre']) ? t('Pon un nombre') : (string)$cat['nombre'];
		$vals[] = (string)$cat['introduccion'];
		$vals[] = empty($cat['notas']) ? '' : $cat['notas']; 
			
		$vals[] = empty($_FILES['imagenes']['name'][0])
			? $cat['fotos'][0]
			: _file::saveFiles($_FILES['imagenes'], 'img/usuarios/' . Session::get('idu'));

		$vals[] = empty($cat['fichas_idu']) ? '' : $cat['fichas_idu']; 
		$vals[] = date('Y-m-d H:i:s');
		$vals[] = empty($cat['publicada']) ? null : date('Y-m-d H:i:s');
		$vals[] = Session::get('idu');
		$vals[] = $idu = (string)$cat['idu'];

		$sql = 'UPDATE aventuras SET usuarios_idu=?, nombre=?, introduccion=?, notas=?, fotos=?, fichas_idu=?, actualizada=?, publicada=? WHERE (usuarios_idu=? OR usuarios_idu IS NULL) AND idu=?';
		parent::query($sql, $vals);
		return $idu;
	}

	#
	public function crear($cat)
	{
		$vals[] = Session::get('idu');
		$vals[] = empty($cat['nombre']) ? t('Pon un nombre') : (string)$cat['nombre'];
		$vals[] = $idu = _str::uid($cat['nombre']);
		$vals[] = (string)$cat['introduccion'];
		$vals[] = empty($cat['notas']) ? '' : $cat['notas'];
        $vals[] = empty($_FILES['imagenes']['name'][0])
            ? $cat['fotos'][0]
			: _file::saveFiles($_FILES['imagenes'], 'img/usuarios/' . Session::get('idu'));

		$vals[] = empty($cat['fichas_idu']) ? '' : $cat['fichas_idu']; 
		$vals[] = date('Y-m-d H:i:s');
		$vals[] = empty($cat['publicada']) ? null : date('Y-m-d H:i:s');

		$sql = 'INSERT INTO aventuras SET usuarios_idu=?, nombre=?, idu=?, introduccion=?, notas=?, fotos=?, fichas_idu=?, creada=?, publicada=?';
		parent::query($sql, $vals);
		return $idu;
	}

	#
	public function eliminar($idu)
	{
		if (Session::get('rol') > 4) {
			$sql = 'DELETE FROM aventuras WHERE idu=?';
			parent::query($sql, [$idu]);
			return;
		}

		$vals[] = Session::get('idu');
		$vals[] = $idu;
		$sql = 'DELETE FROM aventuras WHERE (usuarios_idu=? OR usuarios_idu IS NULL) AND idu=?';
		parent::query($sql, $vals);
	}

	#
	public function todas()
	{
		$sql = 'SELECT * FROM aventuras ORDER BY id DESC';
		return parent::all($sql);
	}

	#
	public function una($idu='')
	{
		$sql = 'SELECT * FROM aventuras WHERE idu=?';
		$una = parent::first($sql, [$idu]);
		return $una ? $una : parent::cols();
	}
}
