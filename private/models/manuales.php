<?php
/**
 */
class Manuales extends LiteRecord
{
	/**
	 * It updates a row in the database with the values passed in the array.
	 * 
	 * @param matriz The array of data to be inserted into the database.
	 * 
	 * @return The idu of the manual.
	 */
	public function actualizar($matriz)
	{
		$values[] = (string)$matriz['fichas_idu'];
		$values[] = Session::get('idu');
		$values[] = empty($matriz['formato']) ? 'letter' : (string)$matriz['formato'];
		$values[] = empty($matriz['plantilla']) ? 'srd20' : (string)$matriz['plantilla'];
		$values[] = empty($matriz['nombre']) ? t('Pon un nombre') : (string)$matriz['nombre'];
		$values[] = (string)$matriz['introduccion'];
		$values[] = (string)$matriz['notas'];
        $values[] = empty($_FILES['imagenes']['name'][0])
            ? $matriz['fotos']
            : (new Archivos)->incluir($_FILES);
		$values[] = empty($matriz['ocultar']) ? 0 : 1;
		$values[] = $idu = (string)$matriz['idu'];

		$sql = 'UPDATE manuales SET fichas_idu=?, usuarios_idu=?, formato=?, plantilla=?, nombre=?, introduccion=?, notas=?, fotos=?, ocultar=? WHERE idu=?';
		self::query($sql, $values);
		return $idu;
	}

	/**
	 * It creates a new manual.
	 * 
	 * @param matriz An array of data to be inserted into the database.
	 * 
	 * @return The idu of the newly created manual.
	 */
	public function crear($matriz)
	{
		$values[] = (string)$matriz['fichas_idu'];
		$values[] = Session::get('idu');
		$values[] = empty($matriz['formato']) ? 'letter' : (string)$matriz['formato'];
		$values[] = empty($matriz['plantilla']) ? 'srd20' : (string)$matriz['plantilla'];
		$values[] = empty($matriz['nombre']) ? t('Pon un nombre') : (string)$matriz['nombre'];
		$values[] = $idu = _str::uid($matriz['nombre']);
		$values[] = (string)$matriz['introduccion'];
		$values[] = (string)$matriz['notas'];
        $values[] = empty($_FILES['imagenes']['name'][0])
            ? $matriz['fotos']
            : (new Archivos)->incluir($_FILES);
		$values[] = empty($matriz['ocultar']) ? 0 : 1;

		$sql = 'INSERT INTO manuales SET fichas_idu=?, usuarios_idu=?, formato=?, plantilla=?, nombre=?, idu=?, introduccion=?, notas=?, fotos=?, ocultar=?';
		self::query($sql, $values);
		return $idu;
	}

	#
	/*public function duplicar($datos)
	{
		$idu = $this->crear($datos);
		$datos['idu_nuevo'] = $idu;
		(new Manuales_reglas)->duplicar($datos);
		return $idu;
	}*/

	/**
	 * It deletes a row from the database
	 * 
	 * @param idu The ID of the manual you want to delete.
	 */
	public function eliminar($idu)
	{
		$sql = 'DELETE FROM manuales WHERE idu=?';
		self::query($sql, [$idu]);
	}

	/**
	 * It returns all the manuals that belong to the user who's currently logged in
	 * 
	 * @return The method returns an array of objects.
	 */
	public function propios()
	{
		$sql = 'SELECT * FROM manuales WHERE usuarios_idu=? ORDER BY nombre';
		return self::all($sql, [Session::get('idu')]);
	}

	/**
	 * It returns all manuals, either all of them or just the ones that belong to a specific user
	 * 
	 * @param idu The id of the user to get the manuals for. If not set, it will get the manuals for the
	 * current user.
	 * 
	 * @return An array of objects.
	 */
	public function todos($idu='')
	{
		$sql = 'SELECT * FROM manuales WHERE ((usuarios_idu=? AND ocultar=1) OR ocultar=0)';
		$values[] = Session::get('idu');
		if ($idu) {
			$sql .= ' AND idu=?';
			$values[] = $idu;
		}
		$sql .= ' ORDER BY nombre';
		return self::all($sql, $values);
	}

	/**
	 * It returns the first row of the query, or an empty array if the query returns no rows
	 * 
	 * @param idu The ID of the user who created the manual.
	 * 
	 * @return The first row of the result set.
	 */
	public function uno($idu='')
	{
		$sql = 'SELECT * FROM manuales WHERE (usuarios_idu=? AND idu=? AND ocultar=1) OR (idu=? AND ocultar=0)';
		$uno = self::first($sql, [Session::get('idu'), $idu, $idu]);
		return $uno ? $uno : parent::cols();
	}
}
