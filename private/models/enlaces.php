<?php
/**
 */
class Enlaces extends LiteRecord
{
	#
	public function acortar($data)
	{
		if ( ! preg_match('/^https?/i', $data['enlace'])) {
			$data['enlace'] = 'https://' . str_ireplace('//', '', $data['enlace']);
		}

		$values[] = Session::get('idu');
		$values[] = $data['nombre'];
		$values[] = $data['enlace'];
		$values[] = _str::uid();
		$values[] = _date::format();

		$sql = 'INSERT INTO enlaces SET usuarios_idu=?, nombre=?, enlace=?, acortado=?, creado=?';
		self::query($sql, $values);
	}

	#
	public function eliminar($acortado)
	{
		$values[] = Session::get('idu');
		$values[] = $acortado;

		$sql = 'DELETE FROM enlaces WHERE usuarios_idu=? AND acortado=?';
		self::query($sql, $values);
	}

	#
	public function sumarUno($acortado)
	{
		$enlace = $this->uno($acortado);

		$values[] = ++$enlace->visitado;
		$values[] = $acortado;

		$sql = 'UPDATE enlaces SET visitado=? WHERE acortado=?';
		self::query($sql, $values);

		return $enlace;
	}

	#
	public function todos()
	{
		$sql = 'SELECT * FROM enlaces';
		return self::all($sql);
	}

	#
	public function uno($acortado)
	{
		$values[] = $acortado;

		$sql = 'SELECT * FROM enlaces WHERE acortado=?';
		return self::first($sql, $values);
	}
}
