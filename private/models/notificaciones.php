<?php
/**
 * Mejorado con ChatGPT 5 razonador el 26 de octubre de 2025
 */
class Notificaciones extends LiteRecord
{
	/* ==========================
	 * Utilidades privadas (DRY)
	 * ========================== */

	/**
	 * Convierte HTML a texto plano y recorta.
	 */
	private function textoPlano(string $txt, int $max = 140): string
	{
		$txt = _html::stripTags($txt);
		// normaliza espacios y saltos
		$txt = preg_replace('/\s+/u', ' ', $txt);
		$txt = trim($txt);
		return _str::truncate($txt, $max);
	}

	/**
	 * Devuelve el icono del usuario si existe; si no, deja null para que
	 * Suscripciones::notificar use su fallback.
	 */
	private function iconoUsuario(?object $usuario): ?string
	{
		if (empty($usuario) || empty($usuario->idu) || empty($usuario->avatar)) {
			return null;
		}
		return "/img/usuarios/{$usuario->idu}/xxs.{$usuario->avatar}";
	}

	/**
	 * Construye URL absoluta a partir de una ruta absoluta de la app.
	 * Si ya es absoluta, la devuelve tal cual.
	 */
	private function urlAbsoluta(string $url): string
	{
		if (preg_match('#^https?://#i', $url)) {
			return $url;
		}
		$host = $_SERVER['HTTP_HOST'] ?? 'roleplus.app';
		// Forzamos https en producción; en local seguirá valiendo http://localhost
		$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
		return "{$scheme}://{$host}{$url}";
	}

	/**
	 * Dispara un push a un usuario concreto.
	 */
	private function enviarPush(
		string $para_idu,
		string $title,
		string $body,
		string $url,
		?string $icon = null
	): void {
		(new Suscripciones)->notificar([
			'usuarios_idu'       => $para_idu,
			'title'              => $this->textoPlano($title, 60),
			'body'               => $this->textoPlano($body, 160),
			'icon'               => $icon ?: '/img/logos/icon-192x192.png',
			'url'                => $this->urlAbsoluta($url),
			'requireInteraction' => false,
		]);
	}

	/**
	 * Inserta en BD una tanda de notificaciones construida en crearTodas().
	 */
	private function insertarLote(array $valores): ?string
	{
		if (empty($valores)) return null;

		$keys = [];
		$vals = [];
		foreach ($valores as $v) {
			$keys[] = '(?, ?, ?, ?, ?, ?, ?, ?)';
			array_push(
				$vals,
				$v['idu'],
				$v['para_idu'],
				$v['de_idu'],
				$v['que'],
				$v['fragmento'],
				$v['donde'],
				$v['donde_idu'],
				$v['cuando']
			);
		}

		$sql = 'INSERT INTO notificaciones (idu, para_idu, de_idu, que, fragmento, donde, donde_idu, cuando) VALUES ' . implode(', ', $keys);
		self::query($sql, $vals);

		// devuelve el último idu insertado para usar como ancla si se necesita
		return end($valores)['idu'] ?? null;
	}

	/* ==========================
	 * Métodos básicos
	 * ========================== */

	# R+2 -> TEST OK
	public function borrarUna($idu)
	{
		$sql = 'UPDATE notificaciones SET borrado=? WHERE para_idu=? AND (idu=? OR donde_idu=?)';
		self::query($sql, [_date::format(), Session::get('idu'), $idu, $idu]);
	}

	# R+2 -> TEST OK
	public function borrarTodas()
	{
		$sql = 'UPDATE notificaciones SET borrado=? WHERE para_idu=?';
		self::query($sql, [_date::format(), Session::get('idu')]);
		Session::setArray('toast', t('¡Notificaciones leídas!'));
	}

	/**
	 * Inserta todas las notificaciones calculadas y notifica por push a cada destino.
	 * $usuarios es un array asociativo [ para_idu => objetoConCampos ]
	 */
	# R+2 -> TEST OK
	public function crearTodas($usuarios)
	{
		// Solo usuarios logeados generan push / notificaciones
		if (!Session::get('idu')) return;

		unset($usuarios[Session::get('idu')]); // evita auto-notificaciones

		if (empty($usuarios)) return;

		$de_idu  = Session::get('idu');
		$ahora   = _date::format();
		$usuario = (new Usuarios)->uno(); // emisor (actual)

		$lote = [];
		foreach ($usuarios as $u) {
			$idu = _str::uid();

			$lote[] = [
				'idu'        => $idu,
				'para_idu'   => $u->para_idu,
				'de_idu'     => $de_idu,
				'que'        => $u->que,
				'fragmento'  => $this->textoPlano("{$u->titulo} — {$u->fragmento}", 160),
				'donde'      => $u->donde,
				'donde_idu'  => $u->donde_idu,
				'cuando'     => $ahora,
			];
		}

		$ultimo_idu = $this->insertarLote($lote);

		// Push one-by-one (un mensaje por usuario destino)
		foreach ($usuarios as $u) {
			$icon = $this->iconoUsuario($usuario);
			$url  = "/registrados/notificaciones/{$u->donde}/{$ultimo_idu}/{$u->donde_idu}";
			$this->enviarPush(
				$u->para_idu,
				$usuario->apodo,
				"{$u->titulo} — {$u->fragmento}",
				$url,
				$icon
			);

			_url::enviarAlCanal("notificaciones_$u->para_idu", [
				'url' => '/registrados/notificaciones/nuevas',
			]);

			_url::enviarAlCanal("notificaciones_lista_$u->para_idu", [
				'url' => '/registrados/notificaciones/lista',
			]);
		}
	}

	# R+2 -> TEST OK
	public function eliminarUna($idu)
	{
		$sql = 'DELETE FROM notificaciones WHERE para_idu=? AND (idu=? OR donde_idu=?)';
		self::query($sql, [Session::get('idu'), $idu, $idu]);
	}

	/**
	 * Notifica a cada usuario del arreglo $usuarios (array de objetos con para_idu, titulo, fragmento, etc.)
	 * Conserva la firma original: $idu no se usa como id de la notificación aquí (lo recalculamos por lote).
	 */
	# R+2 -> TEST OK
	public function notificar($idu, $usuarios)
	{
		// Solo usuarios logeados generan push / notificaciones
		if (!Session::get('idu')) return;

		$usuario = (new Usuarios)->uno(); // emisor

		unset($usuarios[Session::get('idu')]);

		foreach ($usuarios as $u) {
			$icon = $this->iconoUsuario($usuario);
			$url  = "/registrados/notificaciones/{$u->donde}/{$idu}/{$u->donde_idu}";
			$this->enviarPush(
				$u->para_idu,
				$usuario->apodo,
				"{$u->titulo} — {$u->fragmento}",
				$url,
				$icon
			);
		}
	}

	/**
	 * Crea e inserta UNA notificación y dispara el push al destinatario.
	 */
	# R+2
	public function notificarUna($array)
	{
		// Solo usuarios logeados generan push / notificaciones
		if (!Session::get('idu')) return;

		$mensajePlano = $this->textoPlano($array['mensaje'], 160);
		$idu          = _str::uid();
		$de_idu       = Session::get('idu');

		$vals = [
			$idu,
			$array['para_idu'],
			$de_idu,
			$array['que'],
			$mensajePlano,
			$array['donde'],
			$array['donde_idu'],
			_date::format(),
		];

		$sql = 'INSERT INTO notificaciones (idu, para_idu, de_idu, que, fragmento, donde, donde_idu, cuando) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
		self::query($sql, $vals);

		$usuario = (new Usuarios)->uno();
		$icon    = $this->iconoUsuario($usuario);
		$url     = "/registrados/notificaciones/{$array['donde']}/{$idu}/{$array['donde_idu']}";

		$this->enviarPush(
			$array['para_idu'],
			t('Mensaje de ') . $usuario->apodo,
			$mensajePlano,
			$url,
			$icon
		);
	}

	# R+2 -> TEST OK
	public function nuevas()
	{
		$sql = 'SELECT notis.*, usu.apodo, usu.hashtag, usu.avatar, usu.email, usu.idu AS usuarios_idu, usu.rol
				  FROM notificaciones notis, usuarios usu
				 WHERE notis.de_idu=usu.idu AND para_idu=? AND borrado IS NULL
			  ORDER BY notis.cuando DESC
				 LIMIT 50';
		$notis = self::all($sql, [Session::get('idu')]);
		return self::arrayBy($notis);
	}

	# R+2 -> TEST OK
	public function viejas()
	{
		$sql = 'SELECT notis.*, usu.apodo, usu.hashtag, usu.avatar, usu.email, usu.idu AS usuarios_idu, usu.rol
				  FROM notificaciones notis, usuarios usu
				 WHERE notis.de_idu=usu.idu AND para_idu=? AND borrado IS NOT NULL
			  ORDER BY notis.cuando DESC
				 LIMIT 50';
		$notis = self::all($sql, [Session::get('idu')]);
		return self::arrayBy($notis);
	}

	/* ==========================
	 * Métodos complejos (eventos)
	 * ========================== */

	# R+2 -> TEST OK
	public function comentando($pub, $com)
	{
		// Solo usuarios logeados generan push / notificaciones
		if (!Session::get('idu')) return;

		$usuarios = $this->seguidoresPublicacionComentada($pub, $com);
		$usuarios = $this->mencionadosEnComentario($pub, $com, $usuarios);
		$usuarios = $this->sinLosUsuariosQueMeHanBloqueado($usuarios);
		$this->crearTodas($usuarios);
	}

	public function conversando($cov)
	{
		// Implementable si más adelante quieres conversaciones push específicas
	}

	# R+2 -> TEST OK
	public function publicando($pub)
	{
		// Solo usuarios logeados generan push / notificaciones
		if (!Session::get('idu')) return;

		$usuarios = $this->obtenerSeguidoresGrupos($pub);
		$usuarios = $this->obtenerSeguidoresUsuario($pub, $usuarios);
		$usuarios = $this->mencionadosEnPublicacion($pub, $usuarios);
		$usuarios = $this->sinLosUsuariosQueMeHanBloqueado($usuarios);
		$this->crearTodas($usuarios);
	}

	# R+2 -> TEST OK
	public function obtenerSeguidoresGrupos($pub, $usuarios = [])
	{
		$hashtags     = $this->obtenerGrupos($pub->contenido);
		$prioritarios = (new Usuarios_grupos)->enGruposPorHashtags($hashtags);

		return $this->prepararConsulta($usuarios, $prioritarios, [
			'que'        => 'publicando',
			'titulo'     => t('Grupo que sigues...'),
			'fragmento'  => $this->textoPlano($pub->contenido, 80),
			'donde'      => 'publicaciones',
			'donde_idu'  => $pub->idu,
		]);
	}

	# R+2 -> TEST OK
	public function obtenerGrupos($contenido)
	{
		preg_match_all('/#\w+/u', $contenido, $hashtags);
		return empty($hashtags[0]) ? [] : $hashtags[0];
	}

	# R+2 -> TEST OK
	public function obtenerSeguidoresUsuario($pub, $usuarios = [])
	{
		$prioritarios = (new Acciones)->registrosPorIdu(Session::get('idu'), 'usuarios', 'notificar');

		return $this->prepararConsulta($usuarios, $prioritarios, [
			'que'        => 'publicando',
			'titulo'     => t('Usuario que sigues...'),
			'fragmento'  => $this->textoPlano($pub->contenido, 80),
			'donde'      => 'publicaciones',
			'donde_idu'  => $pub->idu,
		]);
	}

	# R+2 -> TEST OK
	public function seguidoresPublicacion($pub, $usuarios = [])
	{
		$seguidores          = (new Acciones)->registrosPorIdu($pub->idu, 'publicaciones', 'notificar');
		$propietario[$pub->usuarios_idu] = (new Usuarios)->uno($pub->usuarios_idu);

		return $this->prepararConsulta($usuarios, $seguidores + $propietario, [
			'que'        => 'publicando',
			'titulo'     => t('Publicación que sigues...'),
			'fragmento'  => $this->textoPlano("{$pub->titulo} - {$pub->contenido}", 80),
			'donde'      => 'publicaciones',
			'donde_idu'  => $pub->idu,
		]);
	}

	# R+2 -> TEST OK
	public function seguidoresPublicacionComentada($pub, $com, $usuarios = [])
	{
		$seguidores          = (new Acciones)->registrosPorIdu($pub->idu, 'publicaciones', 'notificar');
		$propietario[$pub->usuarios_idu] = (new Usuarios)->uno($pub->usuarios_idu);

		return $this->prepararConsulta($usuarios, $seguidores + $propietario, [
			'que'        => 'comentando',
			'titulo'     => t('Comentario en publicación...'),
			'fragmento'  => $this->textoPlano($com->comentario, 80),
			'donde'      => 'publicaciones',
			'donde_idu'  => $pub->idu,
		]);
	}

	# R+2 -> TEST OK
	public function mencionadosEnPublicacion($pub, $usuarios = [])
	{
		$apodos       = $this->obtenerMencionados($pub->contenido);
		$prioritarios = (new Usuarios)->usuariosPorApodos($apodos);

		return $this->prepararConsulta($usuarios, $prioritarios, [
			'que'        => 'mencionando',
			'titulo'     => t('Has sido mencionado...'),
			'fragmento'  => $this->textoPlano($pub->contenido, 80),
			'donde'      => 'publicaciones',
			'donde_idu'  => $pub->idu,
		]);
	}

	# R+2 -> TEST OK
	public function mencionadosEnComentario($pub, $com, $usuarios = [])
	{
		$apodos       = $this->obtenerMencionados($com->comentario);
		$prioritarios = (new Usuarios)->usuariosPorApodos($apodos);

		return $this->prepararConsulta($usuarios, $prioritarios, [
			'que'        => 'mencionando',
			'titulo'     => t('Has sido mencionado...'),
			'fragmento'  => $this->textoPlano($com->comentario, 80),
			'donde'      => 'publicaciones',
			'donde_idu'  => $pub->idu,
		]);
	}

	# R+2 -> TEST OK
	public function obtenerMencionados($contenido)
	{
		preg_match_all('/{@([\w ]+)}/u', $contenido, $hashtags);
		return empty($hashtags[0]) ? [] : $hashtags[0];
	}

	# R+2 -> TEST OK
	public function sinLosUsuariosQueMeHanBloqueado($usuarios)
	{
		$bloqueados = (new Acciones)->registrosPorIdu(Session::get('idu'), 'usuarios', 'bloqueado');
		if (!$bloqueados) return $usuarios;

		foreach ($usuarios as $idu => $usuario) {
			if (!empty($bloqueados[$idu])) unset($usuarios[$idu]);
		}
		return $usuarios;
	}

	# R+2 -> TEST OK
	public function prepararConsulta($usuarios = [], $prioritarios = [], $campos = [])
	{
		if (!$prioritarios) return $usuarios;

		foreach ($prioritarios as $idu => $pri) {
			if (!empty($usuarios[$idu])) unset($usuarios[$idu]);

			$pri->que        = $campos['que'];
			$pri->para_idu   = $idu;
			$pri->titulo     = $campos['titulo'];
			$pri->fragmento  = $campos['fragmento'];
			$pri->donde      = $campos['donde'];
			$pri->donde_idu  = $campos['donde_idu'];
		}
		return $usuarios + $prioritarios;
	}

	# R+2 -> TEST OK
	public function siguiendo($usu)
	{
		// Solo usuarios logeados generan push / notificaciones
		if (!Session::get('idu')) return;

		$para_idu = $usu->idu;
		$de_idu   = Session::get('idu');
		if ($para_idu == $de_idu) return;

		$seguidor = (new Usuarios)->uno($de_idu);

		$usuarios[$para_idu] = (object)[
			'que'        => 'siguiendo',
			'para_idu'   => $para_idu,
			'titulo'     => t('Tiene un nuevo seguidor...'),
			'fragmento'  => $seguidor->apodo,
			'donde'      => 'usuarios',
			'donde_idu'  => $de_idu,
		];

		$usuarios = $this->sinLosUsuariosQueMeHanBloqueado($usuarios);
		$this->crearTodas($usuarios);
	}

	# R+2 -> TEST OK
	public function pxAComentario($com)
	{
		// Solo usuarios logeados generan push / notificaciones
		if (!Session::get('idu')) return;

		$para_idu = $com->usuarios_idu;
		if ((new Configuracion)->unaDeOtro($para_idu, 'no_notificar_masuno')) return;

		$de_idu = Session::get('idu');
		if ($para_idu == $de_idu) return;

		$usuarios[$para_idu] = (object)[
			'que'        => '+1 a comentario',
			'para_idu'   => $para_idu,
			'titulo'     => t('"Me gusta su comentario"'),
			'fragmento'  => $this->textoPlano($com->comentario, 80),
			'donde'      => 'publicaciones',
			'donde_idu'  => $com->publicaciones_idu,
		];

		$usuarios = $this->sinLosUsuariosQueMeHanBloqueado($usuarios);
		$this->crearTodas($usuarios);
	}

	# R+2 -> TEST OK
	public function pxAPublicacion($pub, $cuanto = 1)
	{
		// Solo usuarios logeados generan push / notificaciones
		if (!Session::get('idu')) return;

		$para_idu = $pub->usuarios_idu;
		if ((new Configuracion)->unaDeOtro($para_idu, 'no_notificar_masuno')) return;

		$de_idu = Session::get('idu');
		if ($para_idu == $de_idu) return;

		$titulo = ($cuanto > 1)
			? t('"Me encanta su publicación"')
			: t('"Me gusta su publicación"');

		$usuarios[$para_idu] = (object)[
			'que'        => "+$cuanto a publicación",
			'para_idu'   => $para_idu,
			'titulo'     => $titulo,
			'fragmento'  => $this->textoPlano("{$pub->titulo} - {$pub->contenido}", 80),
			'donde'      => 'publicaciones',
			'donde_idu'  => $pub->idu,
		];

		$usuarios = $this->sinLosUsuariosQueMeHanBloqueado($usuarios);
		$this->crearTodas($usuarios);
	}
}
