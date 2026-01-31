<?php
/**
 */
class Preguntas extends LiteRecord
{
	# 1
	/*public function todas()
	{
		$sql = 'SELECT * FROM preguntas ORDER BY creada DESC';
		return parent::all($sql);
	}

	# 3
	public function una($idu)
	{
		if ( ! $idu) {
			return parent::cols();
		}
		$sql = 'SELECT * FROM respuestas WHERE idu=?';
		return parent::first($sql, [$idu]);
	}

	# 4
	public function crear($post)
	{	
		$vals[] = $idu = _str::uid();
		$vals[] = Session::get('idu');
		$vals[] = $post['pregunta'];
		$vals[] = $post['respuesta'];
		$vals[] = date('Y-m-d H:i:s');
		$vals[] = date('Y-m-d H:i:s');
		$vals[] = 0;
		$sql = 'INSERT INTO respuestas SET idu=?, usuarios_idu=?, pregunta=?, respuesta=?, preguntado=?, respondido=?, retardo=?';
		parent::query($sql, $vals);
		Session::setArray('toast', t('Respuesta creada.'));
		return $idu;
	}

	# 5
	public function actualizar($post)
	{	
		$vals[] = Session::get('idu');
		$vals[] = $post['pregunta'];
		$vals[] = $post['respuesta'];
		$vals[] = date('Y-m-d H:i:s');
		$vals[] = date('Y-m-d H:i:s');
		$vals[] = 0;
		$vals[] = $idu = $post['idu'];
		$sql = 'UPDATE respuestas SET usuarios_idu=?, pregunta=?, respuesta=?, preguntado=?, respondido=?, retardo=? WHERE idu=?';
		parent::query($sql, $vals);
		Session::setArray('toast', t('Respuesta actualizada.'));
		return $idu;
	}

	# 6
	public function eliminar($idu)
	{	
		$sql = 'DELETE FROM respuestas WHERE idu=?';
		parent::query($sql, [$idu]);

		Session::setArray('toast', t('Respuesta eliminada.'));
	}*/
}
