<?php
/**
 */
class Rolflix_suscripciones extends LiteRecord
{
	#
	public function suscribirLista($arr)
	{
		$sql = 'DELETE FROM rolflix_suscripciones WHERE usuarios_idu=?';
		self::query($sql, [Session::get('idu')]);

		if ( ! $arr) {
			return;
		}

        $sql = "INSERT INTO rolflix_suscripciones (usuarios_idu, rolflix_sitios_idu) VALUES";
        foreach ($arr as $rolflix_sitios_idu)
        {
            $sql .= " (?, ?),";
            $values[] = Session::get('idu');
            $values[] = $rolflix_sitios_idu;
        }
        $sql = rtrim($sql, ',');

		self::query($sql, $values);
	}

	#
	public function incluirSuscripcion($rolflix_sitios_idu)
	{
		$sql = 'SELECT * FROM rolflix_suscripciones WHERE usuarios_idu=?';
		$suscripciones = self::all($sql, [Session::get('idu')]);
		$arr[] = $rolflix_sitios_idu;
		foreach ($suscripciones as $obj) {
			$arr[] = $obj->rolflix_sitios_idu;
		}
		$this->suscribirLista($arr);
	}

	#
	public function obtenerSuscripciones()
	{
		$sql = 'SELECT * FROM rolflix_suscripciones WHERE usuarios_idu=?';
		$suscripciones = self::all($sql, [Session::get('idu')]);
		$arr = [];
		foreach ($suscripciones as $obj) {
			$arr[$obj->rolflix_sitios_idu] = $obj;
		}
		return $arr;
	}

	#
	public function quitarSuscripciones($rolflix_sitios_idu)
	{
		$sql = 'DELETE FROM rolflix_suscripciones WHERE rolflix_sitios_idu=?';
		self::query($sql, [$rolflix_sitios_idu]);
	}
}
