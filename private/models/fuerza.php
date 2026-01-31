<?php
/**
 */
class Fuerza extends LiteRecord
{
	#
	public function guardar($post)
	{	
		$vals[] = _str::uid();
		$vals[] = $post['forma'];
		$vals[] = $post['detalles'];
		$vals[] = date('Y-m-d H:i:s');

		$sql = 'INSERT INTO fuerza SET idu=?, forma=?, detalles=?, creado=?';
		self::query($sql, $vals);

		_mail::toAdmin('Fuerza nueva', '<pre>'.print_r($vals, 1));
	}
}
