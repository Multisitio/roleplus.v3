<?php
/**
 * Mejorado con ChatGPT 5 razonador el 26 de octubre de 2025
 */
class Publicidad extends LiteRecord
{
	/* =========
	   Helpers internos
	   ========= */

	/**
	 * idu del usuario en sesión.
	 */
	private function _yo()
	{
		return Session::get('idu');
	}

	/**
	 * Fecha/hora actual (o desplazada) usando tu helper global.
	 */
	private function _ahora($offset = '')
	{
		return _date::format($offset);
	}

	/**
	 * Sube imagen o deja la que había.
	 * OJO: depender de Archivos->incluir($_FILES) implica que ahí valides extensión/MIME/tamaño.
	 */
	private function _resolverImagen($matriz)
	{
        if (
            empty($_FILES['imagenes']['name']) ||
            empty($_FILES['imagenes']['name'][0])
        ) {
            return isset($matriz['imagen']) ? (string)$matriz['imagen'] : '';
        }

        return (new Archivos)->incluir($_FILES); // Riesgo: validar tipo/tamaño en Archivos->incluir()
	}

	/**
	 * Devuelve un registro completo de la publicidad por idu.
	 */
	private function _getAnuncio($idu)
	{
		$sql = 'SELECT * FROM publicidad WHERE idu=?';
		return parent::first($sql, [$idu]);
	}

	/**
	 * Comprueba que el anuncio pertenece al usuario dado.
	 */
	private function _esMio($anuncio, $idu_usuario)
	{
		return ($anuncio && $anuncio->usuarios_idu == $idu_usuario);
	}

	/**
	 * Coste fijo en PX para crear/reactivar.
	 */
	private function _costePx()
	{
		return -500;
	}

	/**
	 * Valor de prioridad inicial.
	 */
	private function _valorBase()
	{
		return 5;
	}

	/**
	 * Devuelve 1 o 0 según checkbox/flag tipo autorenovar.
	 */
	private function _flag($v)
	{
		return empty($v) ? 0 : 1;
	}

	/**
	 * Marca toast.
	 */
	private function _toast($txt)
	{
		Session::setArray('toast', $txt);
	}

	/**
	 * ¿Estamos en CLI?
	 */
	private function _esCLI()
	{
		return (PHP_SAPI === 'cli');
	}

	/**
	 * Log a STDOUT solo en CLI (no afecta a web).
	 */
	private function _log($txt)
	{
		if ($this->_esCLI()) {
			$linea = '[publi] ' . date('Y-m-d H:i:s') . ' ' . $txt . PHP_EOL;
			// STDOUT está siempre definido en CLI
			fwrite(STDOUT, $linea);
		}
	}

	/* =========
	   Actualizar un anuncio existente
	   ========= */

	#
	public function actualizar($matriz)
	{
		$idu_pub = isset($matriz['idu']) ? (string)$matriz['idu'] : '';
		$anuncio = $this->_getAnuncio($idu_pub);

		if (!$anuncio) {
			$this->_toast(t('No se ha encontrado el anuncio.'));
			return;
		}

		if (!$this->_esMio($anuncio, $this->_yo())) {
			$this->_toast(t('No puedes editar este anuncio.'));
			return;
		}

		$values = [
			$this->_yo(),
			empty($matriz['nombre']) ? t('Pon un nombre') : (string)$matriz['nombre'],
			$this->_resolverImagen($matriz),
			isset($matriz['url']) ? (string)$matriz['url'] : '',
			$this->_valorBase(),
			$this->_flag($matriz['autorenovar'] ?? 0),
			$idu_pub
		];

		$sql = 'UPDATE publicidad SET usuarios_idu=?, nombre=?, imagen=?, url=?, valor=?, autorenovar=? WHERE idu=?';
		parent::query($sql, $values);
	}

	/* =========
	   Renovación automática (con log CLI)
	   ========= */

	#
	public function autorenovar()
	{
		$ahora = $this->_ahora();
		$todos = $this->todos();

		$total = 0;
		$autorenovar_total = 0;
		$candidatos = 0;
		$renovados = 0;
		$fallidos = 0;
		$proximas = []; // [caducada, idu, usuarios_idu]

		$this->_log('Inicio autorenovar');

		foreach ($todos as $uno) {
			$total++;

			if (!$uno->autorenovar) {
				continue;
			}
			$autorenovar_total++;

			$cad = isset($uno->caducada) ? (string)$uno->caducada : '';
			$esta_caducada = ($cad === '' || $cad <= $ahora);

			if ($esta_caducada) {
				$candidatos++;
				$ok = $this->reactivar($uno->idu, $uno->usuarios_idu);
				if ($ok) {
					$renovados++;
				} else {
					$fallidos++;
				}
			} else {
				$proximas[] = [$cad, $uno->idu, $uno->usuarios_idu];
			}
		}

		// Ordenamos próximas renovaciones por fecha asc
		if (!empty($proximas)) {
			usort($proximas, function ($a, $b) {
				if ($a[0] == $b[0]) return 0;
				return ($a[0] < $b[0]) ? -1 : 1;
			});
		}

		// Resumen
		$this->_log('Resumen: total=' . $total .
			' autorenovar=' . $autorenovar_total .
			' candidatos=' . $candidatos .
			' renovados=' . $renovados .
			' fallidos=' . $fallidos
		);

		// Mostrar las 10 siguientes programadas
		$max_listar = 10;
		$pendientes = count($proximas);
		if ($pendientes > 0) {
			$this->_log('Siguientes ' . min($max_listar, $pendientes) . ' renovaciones programadas:');
			$lim = ($pendientes > $max_listar) ? $max_listar : $pendientes;
			for ($i = 0; $i < $lim; $i++) {
				$cad = $proximas[$i][0];
				$idu = $proximas[$i][1];
				$uid = $proximas[$i][2];
				$this->_log(' - idu=' . $idu . ' usuario=' . $uid . ' caduca=' . $cad);
			}
			if ($pendientes > $max_listar) {
				$this->_log(' ... y ' . ($pendientes - $max_listar) . ' más.');
			}
		} else {
			$this->_log('No hay renovaciones futuras programadas con autorenovar=1.');
		}

		$this->_log('Fin autorenovar');
	}

	/* =========
	   Elegir anuncio ponderado por "valor"
	   ========= */

	#
	public function barajar()
	{
		$sql = 'SELECT * FROM publicidad WHERE (caducada IS NULL OR caducada>?) AND borrada IS NULL ORDER BY valor DESC';
		$consejos = parent::all($sql, [$this->_ahora()]);

		$total = 0;
		foreach ($consejos as $con) {
			$total += (int)$con->valor;
		}

		if ($total <= 0) {
			return null;
		}

		$tirada = rand(1, $total);

		$acum = 0;
		foreach ($consejos as $con) {
			$acum += (int)$con->valor;
			if ($acum >= $tirada) {
				$this->contabilizar($con->idu);
				return $con;
			}
		}

		return null;
	}

	/* =========
	   Mantenimiento puntual (dar idu a filas viejas)
	   ========= */

	#
	public function arreglo()
	{
		$sql = 'SELECT * FROM publicidad WHERE idu IS NULL';
		$consejos = parent::all($sql);

		foreach ($consejos as $con) {
			$sql = 'UPDATE publicidad SET idu=? WHERE id=?';
			parent::query($sql, [_str::uid(), $con->id]);
		}
	}

	/* =========
	   Contadores mostrada / visitada
	   ========= */

	#
	public function contabilizar($idu, $campo='mostrada')
	{
		// Normalizamos campo
		$campo = ($campo === 'visitada') ? 'visitada' : 'mostrada';

		$anuncio = $this->uno($idu);
		if (!$anuncio) {
			return;
		}

		$contador_actual = isset($anuncio->$campo) ? (int)$anuncio->$campo : 0;
		$nuevo_contador = $contador_actual + 1;

		$sql = "UPDATE publicidad SET $campo=? WHERE idu=?";
		parent::query($sql, [$nuevo_contador, $idu]);
	}

	/* =========
	   Crear un anuncio nuevo
	   ========= */

	#
	public function crear($matriz)
	{
		$idu_pub = _str::uid();
		$yo = $this->_yo();

		// El concepto aquí es único porque $idu_pub es único
		$px_ok = (new Experiencia)->registrar(
			$this->_costePx(),
			'iA',
			$yo,
			t('Consejo publicado: ') . $idu_pub
		);

		if (!$px_ok) {
			$this->_toast(t('No tienes PX para crear consejos.'));
			return;
		}

		$values = [
			$idu_pub,
			$yo,
			empty($matriz['nombre']) ? t('Pon un nombre') : (string)$matriz['nombre'],
			$this->_resolverImagen($matriz),
			isset($matriz['url']) ? (string)$matriz['url'] : '',
			$this->_valorBase(),
			$this->_ahora(),
			$this->_ahora('+30 days'),
			$this->_flag($matriz['autorenovar'] ?? 0)
		];

		$sql = 'INSERT INTO publicidad SET idu=?, usuarios_idu=?, nombre=?, imagen=?, url=?, valor=?, creada=?, caducada=?, autorenovar=?';
		parent::query($sql, $values);

		_mail::send(
			'dj@roleplus.app',
			'Un nuevo consejo ha sido publicado',
			'<pre>' . print_r([$sql, $values], 1)
		);
	}

	/* =========
	   Desactivar (forzar caducidad inmediata)
	   ========= */

	#
	public function desactivar($idu, $usuarios_idu = '')
	{
		$usuarios_idu = $usuarios_idu ?: $this->_yo();

		$anuncio = $this->uno($idu);
		if (!$anuncio) {
			$this->_toast(t('No se ha encontrado el anuncio.'));
			return;
		}

		// Sólo el dueño puede desactivar
		if (!$this->_esMio($anuncio, $usuarios_idu)) {
			$this->_toast(t('No puedes desactivar este anuncio.'));
			return;
		}

		$values = [
			$this->_ahora('-1 second'),
			$anuncio->idu,
			$usuarios_idu
		];

		$sql = 'UPDATE publicidad SET caducada=? WHERE idu=? AND usuarios_idu=?';
		parent::query($sql, $values);

		$this->_toast(t('Publicidad desactivada.'));
	}

	/* =========
	   Eliminar (soft delete con marca borrada)
	   ========= */

	#
	public function eliminar($idu)
	{
		$sql = 'UPDATE publicidad SET borrada=? WHERE idu=?';
		parent::query($sql, [$this->_ahora(), $idu]);
	}

	/* =========
	   Obtener un anuncio propio concreto (para editar en panel)
	   ========= */

	#
	public function miConsejo($idu)
	{
		if (!$idu) {
			return parent::cols();
		}

		$sql = 'SELECT * FROM publicidad WHERE usuarios_idu=? AND idu=?';
		return parent::first($sql, [$this->_yo(), $idu]);
	}

	/* =========
	   Reactivar (renovar 30 días pagando PX otra vez) + log CLI
	   ========= */

	#
	public function reactivar($idu, $usuarios_idu = '')
	{
		$usuarios_idu = $usuarios_idu ?: $this->_yo();

		$concepto = t('Consejo reactivado: ') . $idu . ' ' . _str::uid();

		$px_ok = (new Experiencia)->registrar(
			$this->_costePx(),
			'iA',
			$usuarios_idu,
			$concepto
		);

		if (!$px_ok) {
			$this->_toast(t('No tienes PX para reactivar consejos.'));
			$this->_log('Fallo PX: idu=' . $idu . ' usuario=' . $usuarios_idu);
			return false;
		}

		$anuncio = $this->uno($idu);
		if (!$anuncio) {
			$this->_toast(t('No se ha encontrado el anuncio.'));
			$this->_log('Fallo: anuncio no encontrado idu=' . $idu);
			return false;
		}

		// Sólo el dueño puede reactivar
		if (!$this->_esMio($anuncio, $usuarios_idu)) {
			$this->_toast(t('No puedes reactivar este anuncio.'));
			$this->_log('Fallo permisos: idu=' . $idu . ' usuario=' . $usuarios_idu);
			return false;
		}

		$nueva_caducada = $this->_ahora('+30 days');

		$values = [
			$nueva_caducada,
			$anuncio->idu,
			$usuarios_idu
		];

		$sql = 'UPDATE publicidad SET caducada=? WHERE idu=? AND usuarios_idu=?';
		parent::query($sql, $values);

		$this->_log('Renovado: idu=' . $anuncio->idu . ' usuario=' . $usuarios_idu . ' hasta=' . $nueva_caducada);
		return true;
	}

	/* =========
	   Listas y lecturas
	   ========= */

	#
	public function todos()
	{
		$sql = 'SELECT * FROM publicidad WHERE borrada IS NULL';
		return parent::all($sql);
	}

	#
	public function todosMisConsejos()
	{
		$sql = 'SELECT * FROM publicidad WHERE usuarios_idu=? AND borrada IS NULL ORDER BY creada DESC';
		return parent::all($sql, [$this->_yo()]);
	}

	#
	public function uno($idu)
	{
		$sql = 'SELECT * FROM publicidad WHERE idu=?';
		return parent::first($sql, [$idu]);
	}
}
