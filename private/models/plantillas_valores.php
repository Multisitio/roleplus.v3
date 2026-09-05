<?php
/**
 */
class Plantillas_valores extends LiteRecord
{
	/**
	 * Retorna valores para una propiedad específica.
	 */
	public function por_propiedad($propiedades_idu)
	{
		$sql = 'SELECT * FROM plantillas_valores WHERE propiedades_idu=? ORDER BY valor';
		return self::all($sql, [$propiedades_idu]);
	}
}
