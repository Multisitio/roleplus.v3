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
		$values[] = empty($matriz['orientacion']) ? 'v' : (string)$matriz['orientacion'];
		// Resolver plantilla (clonar si es de otro o renombrar la actual del manual)
		$plantilla_nombre = empty($matriz['plantilla']) ? 'srd20' : (string)$matriz['plantilla'];
		$obj_plantilla = (new Plantillas)->obtenerOCrearPorNombre($plantilla_nombre, Session::get('idu'), "Manual: " . ($matriz['idu'] ?? ''));
		$values[] = $obj_plantilla->nombre;
		$values[] = empty($matriz['nombre']) ? t('Pon un nombre') : (string)$matriz['nombre'];
		$values[] = (string)$matriz['introduccion'];
		
		$fotos = empty($_FILES['imagenes']['name'][0]) ? ($matriz['fotos'] ?? '') : (new Archivos)->incluir($_FILES, "usuarios/".Session::get('idu'));
		$foto_final = is_array($fotos) ? end($fotos) : $fotos;
		$values[] = ($foto_final === 'Array' || empty($foto_final)) ? ($matriz['fotos'] ?? '') : $foto_final;
		
		$alcance = empty($matriz['alcance']) ? 'lectura' : (string)$matriz['alcance'];
		$ocultar = ($alcance === 'privado') ? 1 : 0;
		$values[] = $ocultar;
		$values[] = $alcance;
		$values[] = $idu = (string)$matriz['idu'];

		$sql = 'UPDATE manuales SET fichas_idu=?, usuarios_idu=?, formato=?, orientacion=?, plantilla=?, nombre=?, introduccion=?, fotos=?, ocultar=?, alcance=? WHERE idu=?';
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
		$values[] = empty($matriz['orientacion']) ? 'v' : (string)$matriz['orientacion'];
		$values[] = empty($matriz['plantilla']) ? 'srd20' : (string)$matriz['plantilla'];
		$values[] = empty($matriz['nombre']) ? t('Pon un nombre') : (string)$matriz['nombre'];
		$values[] = $idu = _str::uid($matriz['nombre']);
		$values[] = (string)$matriz['introduccion'];
		
		$fotos = empty($_FILES['imagenes']['name'][0]) ? ($matriz['fotos'] ?? '') : (new Archivos)->incluir($_FILES, "usuarios/".Session::get('idu'));
		$foto_final = is_array($fotos) ? end($fotos) : $fotos;
		$values[] = ($foto_final === 'Array' || empty($foto_final)) ? ($matriz['fotos'] ?? '') : $foto_final;
		
		$alcance = empty($matriz['alcance']) ? 'lectura' : (string)$matriz['alcance'];
		$ocultar = ($alcance === 'privado') ? 1 : 0;
		$values[] = $ocultar;
		$values[] = $alcance;

		$sql = 'INSERT INTO manuales SET fichas_idu=?, usuarios_idu=?, formato=?, orientacion=?, plantilla=?, nombre=?, idu=?, introduccion=?, fotos=?, ocultar=?, alcance=?';
		self::query($sql, $values);
		return $idu;
	}

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
		$idu = $idu ? $idu : Session::get('idu');
		$sql = 'SELECT * FROM manuales WHERE usuarios_idu=? ORDER BY nombre';
		return self::all($sql, [$idu]);
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
		if (empty($idu)) {
			return parent::cols();
		}
		
		$sql = 'SELECT * FROM manuales WHERE idu=?';
		$uno = self::first($sql, [$idu]);
		if (!$uno) {
			return parent::cols();
		}

		$current_user = Session::get('idu');
		$es_propietario = ($uno->usuarios_idu === $current_user);

		if ($es_propietario) {
			// El propietario tiene acceso total
			return $this->prepareUno($uno);
		}

		// Si no es el propietario, depende del alcance:
		// 'privado': no tiene acceso
		// 'lectura', 'oculto', 'editor': puede ver
		if ($uno->alcance === 'privado') {
			return parent::cols();
		}

		return $this->prepareUno($uno);
	}

	private function prepareUno($uno)
	{
		if ($uno && $uno->fotos) {
			$ruta = "img/usuarios/{$uno->usuarios_idu}/l.{$uno->fotos}";
			if ($uno->fotos === 'Array' || !file_exists($ruta)) {
				$uno->fotos = '';
			}
		}
		return $uno;
	}

	/**
	 * Calcula el formato en milímetros para imprimir en KDP (con 6.4mm extra)
	 */
	public static function getKdpFormat($manual)
	{
		$mm_w = 0; $mm_h = 0;
		if ($manual->formato === 'a5') { $mm_w = 148; $mm_h = 210; }
		elseif ($manual->formato === 'a4') { $mm_w = 210; $mm_h = 297; }
		
		if ($mm_w === 0) {
			return strtoupper($manual->formato) . ($manual->orientacion === 'h' ? ' landscape' : '');
		}

		if ($manual->orientacion === 'h') {
			$kdp_w = ($mm_h + 6.4); $kdp_h = ($mm_w + 6.4);
		} else {
			$kdp_w = ($mm_w + 6.4); $kdp_h = ($mm_h + 6.4);
		}
		return "{$kdp_w}mm {$kdp_h}mm";
	}
}
