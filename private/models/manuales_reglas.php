<?php
/**
 */
class Manuales_reglas extends LiteRecord
{
	#
	public function actualizar($matriz)
	{
		$values[] = (string)$matriz['manuales_idu'];
		$values[] = (string)$matriz['manuales_reglas_idu'] ?: 'Ninguno';
		$values[] = empty($matriz['pagina_nueva']) ? 0 : 1;
		$values[] = (string)$matriz['idioma'] ?? 'ES';
		$values[] = (string)$matriz['peso'];
		$values[] = empty($matriz['nombre']) ? t('Pon un nombre') : (string)$matriz['nombre'];
		$values[] = empty($matriz['valor']) ? '' : $matriz['valor'];
		$values[] = (string)$matriz['descripcion'];
		$values[] = _html::bbcode($matriz['descripcion']);
		$values[] = empty($matriz['notas']) ? '' : $matriz['notas'];
        $values[] = empty($_FILES['fotos']['name'][0])
            ? empty($matriz['fotos']) ? '' : implode(',', $matriz['fotos'])
            /*: (new Archivos)->incluir($_FILES);*/
			: _file::saveFiles($_FILES['fotos'], 'img/usuarios/' . Session::get('idu'));
        $values[] = empty($_FILES['fotos']['name'][0])
            ? $matriz['fotos_usuarios_idu'] ?? '' 
            : Session::get('idu');
		$values[] = $idu = (string)$matriz['idu'];

		$sql = 'UPDATE manuales_reglas SET manuales_idu=?, manuales_reglas_idu=?, pagina_nueva=?, idioma=?, peso=?, nombre=?, valor=?, descripcion=?, descripcion_md=?, notas=?, fotos=?, fotos_usuarios_idu=? WHERE idu=?';
		$r = self::query($sql, $values);
		#_var::die([$sql, $values, $r]);
		return $idu;
	}

	#
	public function crear($matriz, $traducir='')
	{
		$values[] = (string)$matriz['manuales_idu'];
		$values[] = (string)$matriz['manuales_reglas_idu'];
		$values[] = empty($matriz['pagina_nueva']) ? 0 : 1;
		$values[] = (string)$matriz['idioma'];
		$values[] = (string)$matriz['peso'];
		$values[] = empty($matriz['nombre']) ? t('Pon un nombre') : (string)$matriz['nombre'];
		$values[] = $idu = _str::uid();
		$values[] = empty($matriz['valor']) ? '' : $matriz['valor'];

		if ($matriz['traducir_al']) {
			$pregunta = "Traduce (con solo una respuesta, solo el texto sin modificar el HTML y conservando toda la estructura HTML, conservando las tabulaciones y saltos de línea y sin añadir más HTML) al idioma iso({$matriz['traducir_al']}) lo siguiente: " . trim($matriz['descripcion']);
			$traduccion = (new Respuestas)->preguntarAOpenAi($pregunta);
			$traduccion = str_replace('<br />', '', $traduccion);
			$matriz['descripcion'] = trim($traduccion);
		}

		$values[] = $matriz['descripcion'];
		$values[] = _html::bbcode($matriz['descripcion']);
		$values[] = empty($matriz['notas']) ? '' : $matriz['notas'];
        $values[] = empty($_FILES['fotos']['name'][0])
            ? empty($matriz['fotos']) ? '' : implode(',', $matriz['fotos'])
            /*: (new Archivos)->incluir($_FILES);*/
			: _file::saveFiles($_FILES['fotos'], 'img/usuarios/' . Session::get('idu'));
        $values[] = empty($_FILES['fotos']['name'][0])
            ? $matriz['fotos_usuarios_idu']
            : Session::get('idu');

		$sql = 'INSERT INTO manuales_reglas SET manuales_idu=?, manuales_reglas_idu=?, pagina_nueva=?, idioma=?, peso=?, nombre=?, idu=?, valor=?, descripcion=?, descripcion_md=?, notas=?, fotos=?, fotos_usuarios_idu=?';
		#_::d([$sql, $values]);
		self::query($sql, $values);
		return $idu;
	}

	#
	public function eliminar($idu)
	{
		$values[] = (string)$matriz['idu'];

		$sql = 'DELETE FROM manuales_reglas WHERE idu=?';
		self::query($sql, [$idu]);
	}

	#
	public function todas($manuales_idu, $idioma='ES')
	{
		$sql = 'SELECT * FROM manuales_reglas WHERE manuales_idu=? AND idioma=? ORDER BY peso, nombre';
		$todas = self::all($sql, [$manuales_idu, $idioma]);
		$res = [];
		foreach ($todas as $una) {
			$res[$una->idu] = $una;
		}
		return $res;
	}

	#
	public function una($idu='')
	{
		$sql = 'SELECT * FROM manuales_reglas WHERE idu=?';
		$una = self::first($sql, [$idu]);
		return $una ? $una : parent::cols();
	}
}
