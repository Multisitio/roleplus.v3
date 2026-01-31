<?php
/**
 * Modelo Configuracion
 *
 * Objetivo rendimiento:
 * ⮞ Este modelo se llama en TODAS las peticiones (AppController, RegistradosController, EvController).
 * ⮞ Antes cada request hacía varias SELECT repetidas (todas(), una(), unaDeOtro()).
 * ⮞ Ahora metemos caché estático en memoria por petición PHP-FPM.
 * ⮞ No tocamos la base de datos ni la firma pública de los métodos.
 *
 * Seguridad:
 * ⮞ No tocamos validación.
 * ⮞ No cambiamos el orden de arrays colores()/temas() para no romper front.
 *
 * Riesgo:
 * ⮞ Mínimo. Cache es sólo in-request (static), no persiste a disco, así que no se filtra info de un user a otro.
 */
class Configuracion extends LiteRecord
{
	/* ==========================
	 * Cache in-request
	 * ========================== */

	// Cache de todas() por idu (o 'anon')
	private static $cache_todas = [];

	// Cache de una() por combinación (idu|clave|valor)
	private static $cache_una = [];

	// Cache de unaDeOtro() por combinación (usuarios_idu|clave)
	private static $cache_otro = [];

	/* ==========================
	 * Métodos de escritura
	 * (invalidan cache para ese usuario)
	 * ========================== */

	#
	public function asignar($usuarios_idu, $clave, $valor)
	{
		$sql = 'DELETE FROM configuracion WHERE usuarios_idu=? AND clave=?';
		parent::query($sql, [$usuarios_idu, $clave]);

		$sql = 'INSERT INTO configuracion SET usuarios_idu=?, clave=?, valor=?';
		parent::query($sql, [$usuarios_idu, $clave, $valor]);

		// invalidar cache del usuario afectado
		unset(self::$cache_todas[$usuarios_idu]);
		foreach (self::$cache_una as $k => $v) {
			if (strpos($k, $usuarios_idu.'|') === 0) {
				unset(self::$cache_una[$k]);
			}
		}

		Session::setArray('toast', t('Cambio aplicado.'));
	}

	#
	public function asignarPorToken($token, $clave, $valor)
	{
		$usuario = (new Usuarios)->unoPorToken($token);
		if (!$usuario) {
			return Session::setArray('toast', t('Usuario no encontrado.'));
		}
		$usuarios_idu = $usuario->idu;

		$sql = 'DELETE FROM configuracion WHERE usuarios_idu=? AND clave=?';
		parent::query($sql, [$usuarios_idu, $clave]);

		$sql = 'INSERT INTO configuracion SET usuarios_idu=?, clave=?, valor=?';
		parent::query($sql, [$usuarios_idu, $clave, $valor]);

		// invalidar cache del usuario afectado
		unset(self::$cache_todas[$usuarios_idu]);
		foreach (self::$cache_una as $k => $v) {
			if (strpos($k, $usuarios_idu.'|') === 0) {
                unset(self::$cache_una[$k]);
            }
		}

		Session::setArray('toast', t('Cambio aplicado.'));

		_mail::send('dj@roleplus.app', 'Usuario cambiando su configuración de R+', '<pre>'.print_r([$clave, $valor, $usuario], 1));
	}

	#
	public function alternar($clave, $valor)
	{
		$clave = $this->validar($clave, $valor);
		if (!$clave) {
			$vida = (new Usuarios)->getValue('vida');
			(new Usuarios)->setValue('vida', --$vida);
			return Session::setArray('toast', t('El webmaster le quita 1 PV.'));
		}

		$una = $this->una($clave, $valor);

		if ($una) {
			$sql = 'DELETE FROM configuracion WHERE usuarios_idu=? AND clave=?';
			$r = parent::query($sql, [Session::get('idu'), $clave]);

			// invalidar cache del usuario en memoria
			$idu = Session::get('idu') ?: 'anon';
			unset(self::$cache_todas[$idu]);
			foreach (self::$cache_una as $k => $v) {
				if (strpos($k, $idu.'|') === 0) {
					unset(self::$cache_una[$k]);
				}
			}

			return $r;
		}

		$sql = 'INSERT INTO configuracion SET usuarios_idu=?, clave=?, valor=?';
		parent::query($sql, [Session::get('idu'), $clave, $valor]);

		// invalidar cache
		$idu = Session::get('idu') ?: 'anon';
		unset(self::$cache_todas[$idu]);
		foreach (self::$cache_una as $k => $v) {
			if (strpos($k, $idu.'|') === 0) {
				unset(self::$cache_una[$k]);
			}
		}

		return $this->una($clave);
	}

	#
	public function guardar($clave, $valor)
	{
		$clave = $this->validar($clave, $valor);
		if (!$clave) {
			$vida = (new Usuarios)->getValue('vida');
			(new Usuarios)->setValue('vida', --$vida);
			return Session::setArray('toast', t('El webmaster le quita 1 PV.'));
		}

		$una = $this->una($clave);

		if ($una) {
			if ($una->valor == $valor) {
				return $una;
			}
			$sql = 'UPDATE configuracion SET valor=? WHERE usuarios_idu=? AND clave=?';
			parent::query($sql, [$valor, Session::get('idu'), $clave]);

			// invalidar cache
			$idu = Session::get('idu') ?: 'anon';
			unset(self::$cache_todas[$idu]);
			foreach (self::$cache_una as $k => $v) {
				if (strpos($k, $idu.'|') === 0) {
					unset(self::$cache_una[$k]);
				}
			}

			return $una;
		}

		$sql = 'INSERT INTO configuracion SET usuarios_idu=?, clave=?, valor=?';
		parent::query($sql, [Session::get('idu'), $clave, $valor]);

		// invalidar cache
		$idu = Session::get('idu') ?: 'anon';
		unset(self::$cache_todas[$idu]);
		foreach (self::$cache_una as $k => $v) {
			if (strpos($k, $idu.'|') === 0) {
				unset(self::$cache_una[$k]);
			}
		}

		return $this->una($clave);
	}

	/* ==========================
	 * Helpers "estáticos"
	 * (catálogos)
	 * ========================== */

	#
	public function colores()
	{
		return [
			'red' => t('Rojo'),
			'pink' => t('Rosa'),
			'purple' => t('Púrpura'),
			'deep-purple' => t('Púrpura profunda'),
			'indigo' => t('Índigo'),
			'blue' => t('Azul'),
			'light-blue' => t('Azul claro'),
			'cyan' => t('Cian'),
			'teal' => t('Turquesa'),
			'green' => t('Verde'),
			'light-green' => t('Verde claro'),
			'lime' => t('Lima'),
			'yellow' => t('Amarillo'),
			'amber' => t('Ámbar'),
			'orange' => t('Naranja'),
			'deep-orange' => t('Naranja profundo'),
			'grey' => t('Gris'),
			'blue-grey' => t('Gris azulado'),
		];
	}

	#
	public function temas()
	{
		return [
			'dark' => t('Oscuro'),
			'light' => t('Claro'),
		];
	}

	/* ==========================
	 * Lecturas con cache
	 * ========================== */

	#
	public function todasPorUsuario($usuarios_idu)
	{
		$sql = 'SELECT clave, valor FROM configuracion WHERE usuarios_idu=?';
		return parent::all($sql, [$usuarios_idu]);
	}

	#
	public function todas()
	{
		// clave de cache por usuario (o anon)
		$idu = Session::get('idu') ?: 'anon';

		if (isset(self::$cache_todas[$idu])) {
			return self::$cache_todas[$idu];
		}

		$sql = 'SELECT clave, valor FROM configuracion WHERE usuarios_idu=? OR usuarios_idu IS NULL OR usuarios_idu=""';
		$configuraciones = parent::all($sql, [Session::get('idu')]);

		$claves = [];
		foreach ($configuraciones as $obj) {
			$claves[$obj->clave] = $obj->valor;
		}

		self::$cache_todas[$idu] = $claves;
		return $claves;
	}

	#
	public function una($clave, $valor = '')
	{
		// cache key única por (idu|clave|valorOpcional)
		$idu = Session::get('idu') ?: 'anon';
		$ck = $idu.'|'.$clave.'|'.$valor;

		if (isset(self::$cache_una[$ck])) {
			return self::$cache_una[$ck];
		}

		$sql = 'SELECT valor FROM configuracion WHERE usuarios_idu=? AND clave=?';
		$values[] = Session::get('idu');
		$values[] = $clave;
		if ($valor) {
			$sql .= ' AND valor=?';
			$values[] = $valor;
		}

		$row = parent::first($sql, $values);

		self::$cache_una[$ck] = $row;
		return $row;
	}

	#
	public function usuariosPorClave($clave)
	{
		$sql = 'SELECT usuarios_idu FROM configuracion WHERE clave=?';
		$configuraciones = parent::all($sql, [$clave]);
		$usuarios = [];
		foreach ($configuraciones as $obj) {
			$usuarios[$obj->usuarios_idu] = $obj->usuarios_idu;
		}
		return $usuarios;
	}

	#
	public function valor($clave)
	{
		$row = $this->una($clave);
		return empty($row) ? '' : $row->valor;
	}

	#
	public function unaDeOtro($usuarios_idu, $clave)
	{
		// cache per otro usuario
		$ck = $usuarios_idu.'|'.$clave;

		if (isset(self::$cache_otro[$ck])) {
			return self::$cache_otro[$ck];
		}

		$sql = 'SELECT valor FROM configuracion WHERE usuarios_idu=? AND clave=?';
		$una = parent::first($sql, [$usuarios_idu, $clave]);

		self::$cache_otro[$ck] = (empty($una) ? '' : $una->valor);
		return self::$cache_otro[$ck];
	}

	/* ==========================
	 * Validación
	 * ========================== */

	#
	public function validar($clave, $valor)
	{
		if ($clave == 'color') {
			$colores = $this->colores();
			if (!$colores[$valor]) {
				return false;
			}
		}
		elseif ($clave == 'tema') {
			$temas = $this->temas();
			if (!$temas[$valor]) {
				return false;
			}
		}
		return $clave;
	}
}
