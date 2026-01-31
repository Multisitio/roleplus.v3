<?php
/**
 */
class Etiquetas_usuarios extends LiteRecord
{
	# Guarda las etiquetas elegidas para filtrar
	public function guardar($arr)
	{	
		$donde = $arr['donde'];
        /*$sql = 'DELETE FROM etiquetas_usuarios WHERE usuarios_idu=? AND donde=?';
        self::query($sql, [Session::get('idu'), $donde]);*/

		unset($arr['donde']);
		foreach ($arr as $tipo => $etiquetas) {
			foreach ($etiquetas as $hashtag) {
				$keys[] = '(?, ?, ?, ?)';
				$vals[] = Session::get('idu');
				$vals[] = $hashtag;
				$vals[] = $tipo;
				$vals[] = $donde;
			}		
		}
        $sql = 'INSERT INTO etiquetas_usuarios (usuarios_idu, hashtag, tipo, donde) VALUES ' . implode(', ', $keys);
        self::query($sql, $vals);
	}

	# 
	public function guardarUnaEnPuede($hashtag)
	{	
        $sql = 'INSERT INTO etiquetas_usuarios SET usuarios_idu=?, hashtag=?, tipo=?, donde=?';
        self::query($sql, [Session::get('idu'), $hashtag, 'publicacion_contenido', 'puede']);
	}
	
	# Quita una etiqueta del filtro.
	public function quitar($hashtag, $donde)
	{	
		$sql = 'DELETE FROM etiquetas_usuarios WHERE usuarios_idu=? AND hashtag=? AND donde=?';
		self::query($sql, [Session::get('idu'), $hashtag, $donde]);
	}
	
	# Lista todas las etiquetas establecidas por el usuario
	public function todas($donde='')
	{	
		if ($donde) {
			$sql = 'SELECT * FROM etiquetas_usuarios WHERE usuarios_idu=? AND donde=?';
			return self::all($sql, [Session::get('idu'), $donde]);
		}
		$sql = 'SELECT * FROM etiquetas_usuarios WHERE usuarios_idu=?';
		return self::all($sql, [Session::get('idu')]);
	}
}
