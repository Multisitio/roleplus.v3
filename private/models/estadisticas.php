<?php
/**
 */
class Estadisticas extends LiteRecord
{
	# 1
	public function insertar($clave, $valor)
	{
		$existe = self::existe($clave, $valor);

		if ( ! $existe) {
			$vals[] = $clave;
			$vals[] = $valor;
		}
		$vals[] = date('Y-m-d H:i:s');
		$vals[] = empty($existe->veces) ? 1 : ++$existe->veces;
		$vals[] = $_SERVER['REMOTE_ADDR'];
		$vals[] = empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];
		$vals[] = $_SERVER['HTTP_USER_AGENT'];

		if ($existe) {
			$vals[] = $clave;
			$vals[] = $valor;
			$sql = 'UPDATE estadisticas SET actualizado=?, veces=?, ip=?, referer=?, browser=? WHERE clave=? AND valor=?';
		}
		else {
			$sql = 'INSERT INTO estadisticas SET clave=?, valor=?, creado=?, veces=?, ip=?, referer=?, browser=?';
		}
		parent::query($sql, $vals);
	}

	# 1.1
	public function existe($clave, $valor)
	{
		$sql = 'SELECT * FROM estadisticas WHERE clave=? AND valor=?';
		return parent::first($sql, [$clave, $valor]);
	}

	#
	public function comentarios()
	{
		$comentarios = (new Comentarios)->all("SELECT usu.apodo, usu.hashtag, com.publicado FROM comentarios com, usuarios usu WHERE com.usuarios_idu=usu.idu AND (com.borrado IS NULL OR com.borrado='')");

		$resultado['totales'] = count($comentarios);

		$resultado['usuarios'] = $this->contar($comentarios, 'apodo');

		$resultado['propios'] = empty($resultado['usuarios'][Session::get('apodo')]) ? 0 : $resultado['usuarios'][Session::get('apodo')];

		$resultado['meses'] = $this->contar($comentarios, 'publicado');

		return $resultado;
	}

	#
	public function contar($filas, $columna)
	{
		foreach ($filas as $col) {

			$clave = ($columna=='publicado')
				? date('Y-m', strtotime($col->$columna))
				: $col->$columna;

			$resultado[$clave] = empty($resultado[$clave]) ? 1 : ++$resultado[$clave];
		}

		if ($columna=='publicado') {
			ksort($resultado);
			array_pop($resultado);
		}
		else {
			arsort($resultado);
		}
		
		return $resultado;
	}

	#
	public function publicaciones()
	{
		$publicaciones = (new Publicaciones)->all("SELECT usu.apodo, usu.hashtag, pub.publicado FROM publicaciones pub, usuarios usu WHERE pub.usuarios_idu=usu.idu AND (pub.borrado IS NULL OR pub.borrado='')");

		$resultado['totales'] = count($publicaciones);

		$resultado['usuarios'] = $this->contar($publicaciones, 'apodo');

		$resultado['propios'] = empty($resultado['usuarios'][Session::get('apodo')]) ? 0 : $resultado['usuarios'][Session::get('apodo')];

		$resultado['meses'] = $this->contar($publicaciones, 'publicado');

		return $resultado;
	}

	/*
	#
	public function usuarios()
	{
		$usuarios = (new Usuarios)->all("SELECT apodo, experiencia FROM usuarios WHERE la_clave IS NOT NULL AND confirmado=1 AND terminos=1");

		$resultado['totales'] = count($usuarios);

		$resultado['usuarios'] = $this->contar($publicaciones, 'apodo');

		$resultado['propios'] = empty($resultado['usuarios'][Session::get('apodo')]) ? 0 : $resultado['usuarios'][Session::get('apodo')];

		$resultado['meses'] = $this->contar($publicaciones, 'publicado');

		return $a;
	}
	
	#
	public function grupos()
	{
		$grupos = (new Grupos)->all("SELECT usuarios_idu, nombre, miembros FROM grupos");
		$a['grupos'] = count($grupos);
		$a['grupos_top_miembros'] = $this->top(Session::get('rol')*10, $grupos, 'miembros', 'DESC');

		$grupos_publicaciones = (new Publicaciones)->all("SELECT id, alcance FROM publicaciones WHERE alcance LIKE '{G:%'");
		$a['grupos_publicaciones'] = count($grupos_publicaciones);
		foreach ($grupos_publicaciones as $pub) {
            $grupo_nombre = explode('{G:', $pub->alcance)[1];
            $grupo_nombre = stristr($grupo_nombre, '/')
                ? explode('/', $grupo_nombre)[0]
                : explode('}', $grupo_nombre)[0];
			$this->contar('grupos_top_n_publicaciones', $grupo_nombre);
		}
		$a += $this->contador;
		arsort($a['grupos_top_n_publicaciones']);
		return $a;
	}
	*/
}
