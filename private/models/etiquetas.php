<?php
/**
 */
class Etiquetas extends LiteRecord
{
	# 1
	public function crearDesdeContenido($contenido)
	{	
		preg_match_all('/[?:\s|^]#([^<\s\.:,]+)/', $contenido, $hashtags);

		if (empty($hashtags[1])) {
			return;
		}

		foreach ($hashtags[1] as $hashtag) {
			$this->crear(['hashtag'=>$hashtag, 'tipo'=>'publicacion_contenido']);
		}
	}

	# 1.1
	public function crear($arr)
	{	
		$una = $this->una($arr);
		if ($una->id) {
			return $this->suma($una);
		}
		$vals[] = _str::hashtag($arr['hashtag']);
		$vals[] = (string)$arr['tipo'];
		$sql = 'INSERT INTO etiquetas SET hashtag=?, tipo=?';
		self::query($sql, $vals);
	}

	# 1.1.1
	public function una($arr)
	{	
		$vals[] = _str::hashtag($arr['hashtag']);
		$vals[] = (string)$arr['tipo'];

		$sql = 'SELECT * FROM etiquetas WHERE hashtag=? AND tipo=?';
		$una = self::first($sql, $vals);
		return $una ? $una : parent::cols();
	}

	# 2
	public function crearDesdeEtiquetas($etiquetas)
	{	
		if ($etiquetas) {
			if (strstr($etiquetas, ' ')) {
				$hashtags = explode(' ', $etiquetas);
				foreach ($hashtags as $hashtag) {
					if ( ! $hashtag) {
						continue;
					}
					$this->crear(['hashtag'=>$hashtag, 'tipo'=>'publicacion_etiquetas']);
				}
			}
			else {
				$this->crear(['hashtag'=>$etiquetas, 'tipo'=>'publicacion_etiquetas']);
			}
		}
	}

	# Resta uno a la etiqueta anterior y suma uno a la etiqueta nueva
	public function actualizar($arr)
	{	
		$arr['hashtag'] = Ia::hashtag($arr['hashtag_anterior']);
		$una = $this->una($arr);
		if ($una->veces > 1) {
			$this->resta($una);
			$this->crea($arr);
			return;
		}
		$this->eliminar($arr);
		$vals[] = Ia::hashtag($arr['hashtag']);
		$vals[] = (string)$arr['tipo'];
		$this->crea($arr);
	}

	# Carga la tabla de las etiquetas que hay en las otras tablas 
	public function cargar()
	{	
		# Se vacia la tabla
		#$sql = 'DELETE FROM etiquetas';
		#self::query($sql);

		#$sql = 'SELECT idioma,tipo,subtipo,contenido FROM publicaciones WHERE borrado IS NULL';
		$sql = 'SELECT usuarios_hashtag FROM publicaciones WHERE borrado IS NULL';
		$publicaciones = (new Publicaciones)->all($sql);
		foreach ($publicaciones as $pub) {
			/*$this->crear(['hashtag'=>$pub->idioma, 'tipo'=>'idioma']);
			$this->crear(['hashtag'=>$pub->tipo, 'tipo'=>'publicacion_tipo']);
			$this->crear(['hashtag'=>$pub->subtipo, 'tipo'=>'publicacion_subtipo']);*/
			$this->crear(['hashtag'=>$pub->usuarios_hashtag, 'tipo'=>'usuarios_hashtag']);

			/*if (strstr($pub->contenido, '#')) {
				$this->crearDesdeContenido($pub->contenido);
			}*/
			#_var::flush();
		}

		/*$sql = 'SELECT hashtag FROM grupos';
		$grupos = (new Grupos)->all($sql);
		foreach ($grupos as $gru) {
			$this->crear(['hashtag'=>$gru->hashtag, 'tipo'=>'publicacion_contenido']);
			_var::flush();
		}

		$sql = 'SELECT hashtag FROM carpetas';
		$carpetas = (new Carpetas)->all($sql);
		foreach ($carpetas as $car) {
			$this->crear(['hashtag'=>$car->hashtag, 'tipo'=>'publicacion_contenido']);
			_var::flush();
		}*/
	}

	# Resta uno a una etiqueta si aparece más de una vez o en caso contrario la elimina
	public function eliminar($arr)
	{	
		$una = $this->una($arr);
		if ($una->veces > 1) {
			return $this->resta($una);
		}
		$sql = 'DELETE FROM etiquetas WHERE id=?';
		self::query($sql, $una->id);
	}
	
	# Resta uno a una etiqueta que ya existe más de una vez
	public function resta($una)
	{	
		$sql = 'UPDATE etiquetas SET veces=? WHEREE id=?';
		self::query($sql, [--$una->veces, $una->id]);
	}
	
	# Suma uno a una etiqueta que ya existe
	public function suma($una)
	{	
		$sql = 'UPDATE etiquetas SET veces=? WHERE id=?';
		self::query($sql, [++$una->veces, $una->id]);
	}
	
	# Lista todas las etiquetas por tipo, cantidad y su nombre
	public function todas()
	{	
		$sql = 'SELECT * FROM etiquetas WHERE hashtag<>"" AND veces>9 ORDER BY FIELD(tipo, "idioma", "publicacion_tipo", "publicacion_subtipo", "publicacion_contenido", "usuarios_hashtag"), veces DESC, hashtag';
		return self::all($sql);
	}
}
