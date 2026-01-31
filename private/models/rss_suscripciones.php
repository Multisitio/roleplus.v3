<?php
/**
 */
class Rss_suscripciones extends LiteRecord
{
	#
	public function suscribirLista($arr)
	{
		$sql = 'DELETE FROM rss_suscripciones WHERE usuarios_idu=?';
		self::query($sql, [Session::get('idu')]);

		if ( ! $arr) {
			return;
		}

        $sql = "INSERT INTO rss_suscripciones (usuarios_idu, rss_sitios_idu) VALUES";
        foreach ($arr as $rss_sitios_idu)
        {
            $sql .= " (?, ?),";
            $values[] = Session::get('idu');
            $values[] = $rss_sitios_idu;
        }
        $sql = rtrim($sql, ',');

		self::query($sql, $values);
	}

	#
	public function incluirSuscripcion($rss_sitios_idu)
	{
		$sql = 'SELECT * FROM rss_suscripciones WHERE usuarios_idu=?';
		$suscripciones = self::all($sql, [Session::get('idu')]);
		/*if (count($suscripciones) > Session::get('rol')*30) {
			return Session::setArray('toast', t('Límite sobrepasado.'));
		}*/
		$arr[] = $rss_sitios_idu;
		foreach ($suscripciones as $obj) {
			$arr[] = $obj->rss_sitios_idu;
		}
		$this->suscribirLista($arr);
	}

	#
	public function obtenerSuscripciones()
	{
		$sql = 'SELECT * FROM rss_suscripciones WHERE usuarios_idu=?';
		$suscripciones = self::all($sql, [Session::get('idu')]);
		$arr = [];
		foreach ($suscripciones as $obj) {
			$arr[$obj->rss_sitios_idu] = $obj;
		}
		return $arr;
	}

	#
	public function quitarSuscripciones($rss_sitios_idu)
	{
		$sql = 'DELETE FROM rss_suscripciones WHERE rss_sitios_idu=?';
		self::query($sql, [$rss_sitios_idu]);
	}
}
