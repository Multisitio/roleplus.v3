<?php
/**
 */
class Codigos extends LiteRecord
{
	#
	public function canjearCodigo($array)
	{	
		$sql = 'SELECT * FROM codigos WHERE codigo=?';
		$codigo = self::first($sql, [(string)$array['codigo']]);

		if ( ! $codigo) {
			return Session::setArray('toast', t('El código introducido no existe.'));
		}
		else if ($codigo->canjeado) {
			return Session::setArray('toast', t('El código ya ha sido canjeado.'));
		}
		else if (Session::get('idu') == $codigo->de_idu) {
			return Session::setArray('toast', t('El código no puede ser cangeado por el mismo usuario que lo generó.'));
		}

		$values[] = Session::get('idu');
		$values[] = date('Y-m-d H:i:s');
		$values[] = (string)$array['codigo'];

		$sql = 'UPDATE codigos SET para_idu=?, canjeado=? WHERE codigo=?';
		self::query($sql, $values);

		(new Experiencia)->registrar($codigo->experiencia, $codigo->de_idu, Session::get('idu'), t('Se ha canjeado el código: ') . $codigo->codigo);
	}

	#
	public function crearCodigo($array)
	{	
		$values[] = _str::uid();
		$values[] = (string)$array['de_idu'];
		$values[] = $experiencia = (int)$array['experiencia'];
		$values[] = date('Y-m-d H:i:s');

		$sql = 'INSERT INTO codigos SET codigo=?, de_idu=?, experiencia=?, creado=?';
		self::query($sql, $values);

		Session::setArray('toast', t('Código generado con ') . "$experiencia PX");
	}

	#
	public function todos()
	{	
		$sql = 'SELECT * FROM codigos ORDER BY id DESC';
		return self::all($sql);
	}
}
