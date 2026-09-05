<?php
/**
 */
class Plantillas_selectores extends LiteRecord
{
	/**
	 * Retorna todos los selectores.
	 */
	public function todos()
	{
		return self::all('SELECT * FROM plantillas_selectores ORDER BY selector');
	}
}
