<?php
/**
 */
class Plantillas_propiedades extends LiteRecord
{
	/**
	 * Retorna todas las propiedades.
	 */
	public function todos()
	{
		return self::all('SELECT * FROM plantillas_propiedades ORDER BY propiedad');
	}
}
