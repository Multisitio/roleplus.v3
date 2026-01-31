<?php
/**
 * Mejorado con ChatGPT 5 razonador el 26 de octubre de 2025
 */
class Experiencia extends LiteRecord
{
	/* =========
	   Helpers internos (privados)
	   ========= */

	/**
	 * Devuelve el idu de la sesión actual (usuario logueado).
	 */
	private function _yo()
	{
		return Session::get('idu');
	}

	/**
	 * Devuelve un registro de usuarios con su experiencia actual.
	 * Retorna null si no existe.
	 */
	private function _getUsuario($idu)
	{
        $sql = 'SELECT experiencia FROM usuarios WHERE idu=?';
        return Usuarios::first($sql, [$idu]);
	}

	/**
	 * Actualiza la experiencia total de un usuario concreto.
	 */
	private function _setUsuarioXP($idu, $xp)
	{
		$sql = 'UPDATE usuarios SET experiencia=? WHERE idu=?';
		Usuarios::query($sql, [$xp, $idu]);
	}

	/**
	 * Envía un toast sólo si el afectado es el propio usuario logueado.
	 */
	private function _toastSiSoyYo($idu, $msg)
	{
		if ($this->_yo() == $idu) {
			Session::setArray('toast', $msg);
		}
	}

	/**
	 * Texto base reutilizado para listar quién ha dado PX en comentarios/publicaciones.
	 * (Mismo SQL con JOIN implícito que ya usabas, sólo centralizado.)
	 */
	private function _sqlQuienDaPx()
	{
		return 'SELECT exp.*, exp.de as usuarios_idu, ' .
			'usu.apodo, usu.hashtag, usu.avatar, usu.email, usu.eslogan ' .
			'FROM experiencia exp, usuarios usu ' .
			'WHERE exp.elemento=? AND exp.idu=? AND exp.para=? AND exp.de=usu.idu';
	}

	/* =========
	   Puntos en comentarios
	   ========= */

	#
	public function comentarios($idu)
	{
		$yo = $this->_yo();
		$com = (new Comentarios)->uno($idu);

		// No puedes darte PX a ti mismo.
		if ($yo == $com->usuarios_idu) {
			return;
		}

		// ¿Ya existe registro experiencia para este comentario entre yo -> autor?
		$sql = 'SELECT id FROM experiencia WHERE elemento=? AND idu=? AND de=? AND para=?';
		$el = self::first($sql, ['comentarios', $com->idu, $yo, $com->usuarios_idu]);

		if (empty($el->id)) {
			// No existe → creamos (sumar)
			$sql = 'INSERT INTO experiencia SET elemento=?, idu=?, de=?, para=?';
			self::query($sql, ['comentarios', $com->idu, $yo, $com->usuarios_idu]);
			$accion = 'sumar';
		}
		else {
			// Ya existía → lo eliminamos (restar)
			$sql = 'DELETE FROM experiencia WHERE id=?';
			self::query($sql, [$el->id]);
			$accion = 'restar';
		}

		/* SUMAR AL COMENTARIO */
		$sql = 'SELECT experiencia FROM comentarios WHERE idu=?';
		$comentario = Comentarios::first($sql, [$com->idu]);

		// Igual que tu versión original: ++ / -- directo
		$comentario_experiencia = ($accion === 'sumar')
			? ++$comentario->experiencia
			: --$comentario->experiencia;

		$sql = 'UPDATE comentarios SET experiencia=? WHERE idu=?';
		Comentarios::query($sql, [$comentario_experiencia, $com->idu]);

		/* SUMAR AL USUARIO (autor del comentario) */
		$sql = 'SELECT experiencia FROM usuarios WHERE idu=?';
		$usuario = Usuarios::first($sql, [$com->usuarios_idu]);

		$usuario_experiencia = ($accion === 'sumar')
			? ++$usuario->experiencia
			: --$usuario->experiencia;

		$this->_setUsuarioXP($com->usuarios_idu, $usuario_experiencia);

		# Notificamos el +1 al autor del comentario
		if ($accion === 'sumar') {
			(new Notificaciones)->pxAComentario($com);
		}

		return $comentario_experiencia;
	}

	public function enComentario($comentarios_idu)
	{
		$sql = $this->_sqlQuienDaPx();
		return self::all($sql, ['comentarios', $comentarios_idu, $this->_yo()]);
	}

	/* =========
	   Puntos en publicaciones
	   ========= */

	#
	public function enPublicacion($publicaciones_idu)
	{
		$sql = $this->_sqlQuienDaPx();
		return self::all($sql, ['publicaciones', $publicaciones_idu, $this->_yo()]);
	}

	#
	public function entregada($sitios)
	{
		// $sitios es una colección de objetos con ->idu
		$keys = [];
		$vals = [$this->_yo()];

		foreach ($sitios as $pub) {
			$keys[] = '?';
			$vals[] = $pub->idu;
		}

		$keys_str = implode(', ', $keys);

		// Si no hay nada que consultar devolvemos array vacío
		if (!$keys_str) {
			return [];
		}

		$sql = "SELECT cuanto,elemento,idu FROM experiencia WHERE de=? AND idu IN ($keys_str)";
		$arr = self::all($sql, $vals);

		$experiencia = [];
		foreach ($arr as $obj) {
			$experiencia[$obj->elemento][$obj->idu] = $obj->cuanto;
		}

		return $experiencia;
	}

	#
	public function publicaciones($idu, $cuanto=1)
	{
		$yo = $this->_yo();
		$pub = (new Publicaciones)->una($idu);

		// No puedes darte PX a ti mismo.
		if ($yo == $pub->usuarios_idu) {
			return Session::setArray('toast', t('¡No se puede otorgar experiencia a uno mismo!'));
		}

		// ¿Ya has valorado esta publicación?
		$sql = 'SELECT id,cuanto FROM experiencia WHERE elemento=? AND idu=? AND de=? AND para=?';
		$el = self::first($sql, ['publicaciones', $pub->idu, $yo, $pub->usuarios_idu]);

		if (empty($el->id)) {
			// Primera vez → INSERT
			$sql = 'INSERT INTO experiencia SET elemento=?, idu=?, de=?, para=?, cuanto=?';
			self::query($sql, ['publicaciones', $pub->idu, $yo, $pub->usuarios_idu, $cuanto]);
			$accion = 'sumar';
			$cambio = 0;
		}
		else {
			// Ya habías dado PX → borramos para restar
			$sql = 'DELETE FROM experiencia WHERE id=?';
			self::query($sql, [$el->id]);
			$accion = 'restar';

			// ¿El valor anterior era distinto del nuevo "cuanto"? (me gusta ⇄ me encanta)
			$cambio = ($el->cuanto != $cuanto) ? 1 : 0;
		}

		/* SUMAR A LA PUBLICACION */
		$sql = 'SELECT experiencia FROM publicaciones WHERE idu=?';
		$publicacion = Publicaciones::first($sql, [$pub->idu]);

		$publicacion_experiencia = ($accion === 'sumar')
			? ($publicacion->experiencia + $cuanto)
			: ($publicacion->experiencia - $el->cuanto);

		$sql = 'UPDATE publicaciones SET experiencia=? WHERE idu=?';
		Publicaciones::query($sql, [$publicacion_experiencia, $pub->idu]);

		/* SUMAR AL USUARIO (autor de la publicación) */
		$sql = 'SELECT experiencia FROM usuarios WHERE idu=?';
		$usuario = Usuarios::first($sql, [$pub->usuarios_idu]);

		$usuario_experiencia = ($accion === 'sumar')
			? ($usuario->experiencia + $cuanto)
			: ($usuario->experiencia - $el->cuanto);

		$this->_setUsuarioXP($pub->usuarios_idu, $usuario_experiencia);

		/* Cambio de me encanta ⇄ me gusta
		   Si $cambio es 1 llamamos otra vez con el nuevo valor para reflejar
		   la actualización de "cuanto", igual que hacías.
		   Esto mantiene tu comportamiento.
		*/
		if ($cambio) {
			return self::publicaciones($idu, $cuanto);
		}

		/* Notificamos el más uno al autor de la publicación */
		if ($accion === 'sumar') {
			(new Notificaciones)->pxAPublicacion($pub, $cuanto);
		}

		return $publicacion_experiencia;
	}

	/* =========
	   Registro libre de PX (compras, costes, recompensas…)
	   ========= */

	public function registrar($px, $de, $para, $concepto)
	{
		// 1 ⮞ ¿ya se aplicó este concepto? Evita duplicar cargos/abonos.
		$sql = 'SELECT id FROM experiencia_registro WHERE concepto=?';
		$hay = self::first($sql, [$concepto]);
		if ($hay) {
			return false;
		}

		// 2 ⮞ usuario destino debe existir
		$usu = $this->_getUsuario($para);
		if (empty($usu)) {
			Session::setArray('toast', 'No se ha encontrado el usuario destino.');
			return false;
		}

		// 3 ⮞ normalizamos valores numéricos
		$px = (int)$px;
		$actual = (int)$usu->experiencia;
		$nueva = $actual + $px;

		// 4 ⮞ no dejar saldo negativo
		if ($nueva < 0) {
			Session::setArray('toast', t('No tienes suficiente experiencia.'));
			return false;
		}

		// 5 ⮞ registramos movimiento
		$sql = 'INSERT INTO experiencia_registro SET px=?, de=?, para=?, concepto=?';
		self::query($sql, [$px, $de, $para, $concepto]);

		// 6 ⮞ aplicamos saldo
		$this->_setUsuarioXP($para, $nueva);

		// 7 ⮞ feedback si soy yo
		$this->_toastSiSoyYo($para, "HECHO,<br>hemos ajustado $px PX.");

		return true;
	}
}
