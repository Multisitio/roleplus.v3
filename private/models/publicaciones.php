<?php
/**
 * Modelo: Publicaciones (CRUD + listados y filtros)
 * Notas:
 * ✦ KISS & DRY. Sin @ para silenciar errores.
 * ✦ Tabs reales (ancho 4). Sin comillas tipográficas ni diff markers.
 */
class Publicaciones extends LiteRecord
{
	#
	public function agregaComentarios($idu)
	{
		$sql = 'SELECT com.*, usu.apodo, usu.hashtag, usu.avatar, usu.email, usu.rol, usu.hashtag FROM comentarios com, usuarios usu WHERE com.usuarios_idu=usu.idu AND com.publicaciones_idu=? ORDER BY com.publicado DESC LIMIT 3';
		$ultimos_comentarios = (new Comentarios)->all($sql, [$idu]);

		if (!$ultimos_comentarios) {
			return $this->sinComentarios($idu);
		}

		foreach ($ultimos_comentarios as $com) {
			$fragmentos[] = '<b>' . $com->apodo . ':</b> ' . _str::truncate(strip_tags($com->comentario_formateado));
		}
		$fragmentos = array_reverse($fragmentos);
		$fragmentos = implode('<hr>', $fragmentos);

		$sql = 'UPDATE publicaciones SET fragmentos=? WHERE idu=?';
		self::query($sql, [$fragmentos, $idu]);
	}

	#
	public function comentario($publicaciones_idu)
	{
		$n_comentarios = (new Comentarios)->contar($publicaciones_idu);

		$sql = 'UPDATE publicaciones SET comentarios=? WHERE idu=?';
		self::query($sql, [$n_comentarios, $publicaciones_idu]);
	}

	#
	/*public function compartirDesdeFuera($request) { ... } */

	#
	public function comprobarSiExiste($titulo, $contenido)
	{
		$sql = 'SELECT id FROM publicaciones WHERE titulo=? AND contenido=?';
		return self::first($sql, [$titulo, $contenido]);
	}

	#
	public function crear($a)
	{
		#_var::die($a);

		(new Usuarios)->actualizar('ultima_pub', _date::format());

		$a['action'] = empty($a['action']) ? 'crear' : $a['action'];
		$idu = ($a['action'] == 'crear') ? _str::uid() : $a['idu'];

		$pub_anterior = parent::where('idu=?', [$idu])->row();

		$a['usuarios_idu'] = $usuarios_idu = empty($a['usuarios_idu'])
			? Session::get('idu')
			: $a['usuarios_idu'];

		$a['apodo'] = Session::get('apodo');
		$usuarios_hashtag = empty($a['usuarios_hashtag'])
			? _str::hashtag(Session::get('apodo'))
			: $a['usuarios_hashtag'];

		if (empty($a['idioma'])) {
			$a['idioma'] = 'ES';
		}
		$idioma = empty($a['idioma_otro']) ? _str::hashtag($a['idioma']) : _str::hashtag($a['idioma_otro']);
		if (!$idioma) {
			$idioma = 'ES';
		}

		if (!empty($a['etiquetas'])) {
			$etiquetas = trim($a['etiquetas']);
			if (strstr($etiquetas, ' ')) {
				$etiquetas_separadas = explode(' ', $etiquetas);
			} else {
				$etiquetas_separadas = [$etiquetas];
			}
			$etiquetas = [];
			foreach ($etiquetas_separadas as $eti) {
				if (!$eti) {
					continue;
				}
				$etiquetas[] = $hashtag = _str::hashtag($eti);
				(new Etiquetas)->crear(['hashtag' => $hashtag, 'tipo' => 'publicacion_contenido']);
				$etiquetas_formateadas[] =
					"<a class=\"tag\" href=\"/publicaciones/buscar/$hashtag\">$hashtag</a>";
			}
			$etiquetas = implode(' ', $etiquetas);
			$etiquetas_formateadas = implode(' ', $etiquetas_formateadas);
		} else {
			$etiquetas = '';
			$etiquetas_formateadas = '';
		}

		$titulo = trim($a['titulo']);
		if (!$titulo) {
			return Session::setArray('toast', t('Un título es requerido.'));
		}

		$slug = _url::slug($titulo);
		$slug = ($pub_anterior && $pub_anterior->titulo === $titulo)
			? $pub_anterior->slug
			: parent::getSlug('publicaciones', $slug);

		$a['slug'] = $slug;

		$contenido = $contenido_formateado = trim($a['contenido']);
		if (!$contenido) {
			return Session::setArray('toast', t('Se requiere texto de contenido.'));
		}

		$contenido_formateado = _html::bbcode($contenido_formateado);
		$contenido_formateado = _html::links($contenido_formateado);

		/* ⮞ Fix: asignación correcta de enlace al guardar preview.
		 * Antes: $arr['enlace'] = '' (typo). Ahora: $a['enlace'] = ''.
		 */
		if (!empty($a['preview'])) {
			if (stristr($a['preview']['url'], 'youtu')) {
				$a['enlace'] = $a['preview']['url'];
			} else {
				(new Vistas_previas)->guardarVistas($idu, $a['preview']);
				$a['enlace'] = '';
			}
		}
		if (!empty($a['enlace']) && preg_match('#https?://[^\s]+#', trim($a['enlace']), $m)) {
			$enlace = $m[0];
		} else {
			$enlace = '';
		}

		$a['fotos'] = $fotos = self::gestionarFotos($a);

		$publicado = date('Y-m-d H:i:s');

		$evento_aforo = empty($a['evento_aforo']) ? 0 : $a['evento_aforo'];
		$evento_aforo_minimo = empty($a['evento_aforo_minimo']) ? 0 : $a['evento_aforo_minimo'];
		$evento_desde = empty($a['evento_desde'])
			? '0000-00-00 00:00:00'
			: str_replace('T', ' ', $a['evento_desde']);
		$evento_hasta = empty($a['evento_hasta'])
			? '0000-00-00 00:00:00'
			: str_replace('T', ' ', $a['evento_hasta']);
		if (!empty($a['evento_hasta']) && $evento_hasta <= $evento_desde) {
			$evento_hasta = date('Y-m-d H:i:s', strtotime('+1 hour', strtotime($evento_desde)));
		}

		/* ⮞ Fix: evitar acceso a propiedad de null y aclarar precedencias.
		 * Antes: (int)$pub_anterior->encuesta ?? 0 → podría romper si $pub_anterior es null.
		 */
		if (isset($a['encuesta'])) {
			$encuesta = (int)$a['encuesta'];
		} else {
			$encuesta = (int)($pub_anterior->encuesta ?? 0);
		}

		$usuario = (new Usuarios)->uno();

		$anclado = empty($a['anclado']) ? '0000-00-00 00:00:00' : date('Y-m-d H:i:s');

		if ($a['action'] == 'editar') {
			$pub = (new Publicaciones)->una($idu);
			if (!$pub) {
				Session::setArray('toast', 'Nada que actualizar.');
			}
			if ($pub->anclado < date('Y-m-d H:i:s', strtotime('-24 hours')) && $anclado <> '0000-00-00 00:00:00') {
				if ($usuario->experiencia > 99) {
					(new Experiencia)->registrar(-100, 'iA', Session::get('idu'), t('Publicación anclada: ') . $idu);
				} else {
					$anclado = '0000-00-00 00:00:00';
					Session::setArray('toast', 'No tienes PX para anclar.');
				}
			}
			$sql = 'UPDATE publicaciones SET idioma=?, etiquetas=?, etiquetas_formateadas=?, titulo=?, slug=?, contenido=?, contenido_formateado=?, enlace=?, fotos=?, evento_aforo=?, evento_aforo_minimo=?, evento_desde=?, evento_hasta=?, encuesta=?, anclado=? WHERE usuarios_idu=? AND idu=?';
			self::query($sql, [$idioma, $etiquetas, $etiquetas_formateadas, $titulo, $slug, $contenido, $contenido_formateado, $enlace, $fotos, $evento_aforo, $evento_aforo_minimo, $evento_desde, $evento_hasta, $encuesta, $anclado, Session::get('idu'), $idu]);
			Session::setArray('toast', 'Publicación editada.');
		} else {
			if ($this->comprobarSiExiste($titulo, $contenido)) {
				return Session::setArray('toast', t('Ya existe una publicación con el mismo título y contenido.'));
			}

			if ($anclado <> '0000-00-00 00:00:00') {
				if ($usuario->experiencia > 99) {
					(new Experiencia)->registrar(-100, 'iA', Session::get('idu'), t('Publicación anclada: ') . $idu);
				} else {
					$anclado = '0000-00-00 00:00:00';
					Session::setArray('toast', 'No tienes PX para anclar.');
				}
			}
			$sql = 'INSERT INTO publicaciones SET usuarios_idu=?, usuarios_hashtag=?, idioma=?, etiquetas=?, etiquetas_formateadas=?, titulo=?, slug=?, contenido=?, contenido_formateado=?, idu=?, enlace=?, fotos=?, publicado=?, evento_aforo=?, evento_aforo_minimo=?, evento_desde=?, evento_hasta=?, encuesta=?, anclado=?';

			$vals = [$usuarios_idu, $usuarios_hashtag, $idioma, $etiquetas, $etiquetas_formateadas, $titulo, $slug, $contenido, $contenido_formateado, $idu, $enlace, $fotos, $publicado, $evento_aforo, $evento_aforo_minimo, $evento_desde, $evento_hasta, $encuesta, $anclado];

			self::query($sql, $vals);
			Session::setArray('toast', 'Publicación creada.');
			(new Acciones)->crear('publicaciones', $idu, 'notificar');
		}

		(new Etiquetas)->crear(['hashtag' => $usuarios_hashtag, 'tipo' => 'usuarios_hashtag']);
		(new Etiquetas)->crear(['hashtag' => $idioma, 'tipo' => 'idioma']);
		(new Etiquetas)->crearDesdeEtiquetas($etiquetas);
		(new Etiquetas)->crearDesdeContenido($contenido);

		if ($a['action'] <> 'editar') {
			$pub = empty($pub) ? (new Publicaciones)->una($idu) : $pub;
			(new Notificaciones)->publicando($pub, $idu);
		}

		if ($encuesta && $a['action'] <> 'editar') {
			(new Encuestas)->salvar($idu, $a['opciones']);
		}

		$conectados = (new Usuarios)->conectados();
		foreach ($conectados as $conectado) {
			$arr[] = "publicaciones_$conectado->idu";
		}

		_url::enviarAlCanal('publicaciones_nuevas', [
			'url' => '/registrados/publicaciones/nuevas',
		]);

		if ($anclado <> '0000-00-00 00:00:00' && !empty($a['anclado']) && $a['action'] == 'crear') {
			(new Twitter)->enviarTwit($a, $fotos);
		}

		if ($a['action'] == 'crear') {
			(new Telegram)->send(1, $a);
		}

		return parent::where('idu=?', [$idu])->row();
	}

	#
	public function gestionarFotos($a)
	{
		$fotos = '';

		if (!empty($a['fotos'])) {
			foreach ($a['fotos'] as $foto) {
				if (!$foto) {
					continue;
				}
				$fotos .= "$foto, ";
			}
		}
		if (!empty($_FILES['fotos'])) {
			$fotos .= _file::saveFiles($_FILES['fotos'], 'img/usuarios/' . Session::get('idu'));
		}

		$fotos = trim($fotos, ', ');

		return $fotos;
	}

	#
	public function eliminar($idu)
	{
		$sql = 'DELETE FROM publicaciones WHERE usuarios_idu=? AND idu=?';
		self::query($sql, [Session::get('idu'), $idu]);

		(new Notificaciones)->borrarUna($idu);

		Session::setArray('toast', '¡Eliminado!');
	}

	#
	public function eliminarEnlace($idu)
	{
		$vals[] = Session::get('idu');
		$vals[] = $idu;

		$sql = 'UPDATE publicaciones SET enlace="" WHERE usuarios_idu=? AND idu=?';
		self::query($sql, $vals);

		Session::setArray('toast', 'Enlace eliminado.');
	}

	#
	public function filtroPorEtiquetas()
	{
		$etiquetas_usuario = (new Etiquetas_usuarios)->todas();
		$and = $not = $or = $vals = [];
		$i = 0;
		foreach ($etiquetas_usuario as $e_u) {
			if ($e_u->donde == 'debe_contener') {
				if ($e_u->tipo == 'idioma') {
					$and['idioma'][$i] = 'idioma=?';
					$vals[$i] = $e_u->hashtag;
				} elseif ($e_u->tipo == 'publicacion_tipo') {
					$and['tipo'][$i] =  'tipo=?';
					$vals[$i] = $e_u->hashtag;
				} elseif ($e_u->tipo == 'publicacion_subtipo') {
					$and['subtipo'][$i] = 'subtipo=?';
					$vals[$i] = $e_u->hashtag;
				} elseif ($e_u->tipo == 'publicacion_contenido') {
					$and['contenido_formateado'][$i] = 'contenido LIKE ?';
					$vals[$i] = "% #$e_u->hashtag %";
				} elseif ($e_u->tipo == 'usuarios_hashtag') {
					$and['usuarios_hashtag'][$i] = 'usuarios_hashtag=?';
					$vals[$i] = $e_u->hashtag;
				}
			} elseif ($e_u->donde == 'puede_contener') {
				if ($e_u->tipo == 'idioma') {
					$or['idioma'][$i] = 'idioma=?';
					$vals[$i] = $e_u->hashtag;
				} elseif ($e_u->tipo == 'publicacion_tipo') {
					$or['tipo'][$i] = 'tipo=?';
					$vals[$i] = $e_u->hashtag;
				} elseif ($e_u->tipo == 'publicacion_subtipo') {
					$or['subtipo'][$i] = 'subtipo=?';
					$vals[$i] = $e_u->hashtag;
				} elseif ($e_u->tipo == 'publicacion_contenido') {
					$or['contenido_formateado'][$i] = 'contenido LIKE ?';
					$vals[$i] = "% #$e_u->hashtag %";
				} elseif ($e_u->tipo == 'usuarios_hashtag') {
					$or['usuarios_hashtag'][$i] = 'usuarios_hashtag=?';
					$vals[$i] = $e_u->hashtag;
				}
			} elseif ($e_u->donde == 'no_listar') {
				if ($e_u->tipo == 'idioma') {
					$not['idioma'][$i] = 'idioma=?';
					$vals[$i] = $e_u->hashtag;
				} elseif ($e_u->tipo == 'publicacion_tipo') {
					$not['tipo'][$i] = 'tipo=?';
					$vals[$i] = $e_u->hashtag;
				} elseif ($e_u->tipo == 'publicacion_subtipo') {
					$not['subtipo'][$i] = 'subtipo=?';
					$vals[$i] = $e_u->hashtag;
				} elseif ($e_u->tipo == 'publicacion_contenido') {
					$not['contenido_formateado'][$i] = 'contenido LIKE ?';
					$vals[$i] = "% #$e_u->hashtag %";
				} elseif ($e_u->tipo == 'usuarios_hashtag') {
					$not['usuarios_hashtag'][$i] = 'usuarios_hashtag=?';
					$vals[$i] = $e_u->hashtag;
				}
			}
			++$i;
		}
		$sql = '';
		$values = [];
		foreach ($and as $tipo => $fields) {
			$sql .= ' AND ' . implode(' AND ', $and[$tipo]);
			foreach ($fields as $i => $_) {
				$values[] = $vals[$i];
			}
		}
		foreach ($or as $tipo => $fields) {
			$sql .= ' AND (' . implode(' OR ', $or[$tipo]) . ')';
			foreach ($fields as $i => $_) {
				$values[] = $vals[$i];
			}
		}
		foreach ($not as $tipo => $fields) {
			$sql .= ' AND NOT ' . implode(' AND NOT ', $not[$tipo]);
			foreach ($fields as $i => $_) {
				$values[] = $vals[$i];
			}
		}
		return ['sql' => $sql, 'vals' => $values];
	}

	#
	public function etiquetas($usuarios_idu)
	{
		$sql = "SELECT idioma, tipo, subtipo, etiquetas, contenido FROM publicaciones WHERE usuarios_idu=?";
		$publicaciones = self::all($sql, [$usuarios_idu]);
		$etiquetas = [];
		foreach ($publicaciones as $pub) {
			$etiquetas = _array::sumaUnoSiExiste($etiquetas, $pub->idioma);
			if ($pub->tipo) {
				$etiquetas = _array::sumaUnoSiExiste($etiquetas, $pub->tipo);
			}
			if ($pub->subtipo) {
				$etiquetas = _array::sumaUnoSiExiste($etiquetas, $pub->subtipo);
			}

			if ($pub->etiquetas) {
				if (strstr($pub->etiquetas, ' ')) {
					$hashtags = explode(' ', $pub->etiquetas);
					foreach ($hashtags as $hashtag) {
						if (!$hashtag) {
							continue;
						}
						$etiquetas = _array::sumaUnoSiExiste($etiquetas, $hashtag);
					}
				} else {
					$etiquetas = _array::sumaUnoSiExiste($etiquetas, $pub->etiquetas);
				}
			}

			$hashtags = [];
			preg_match_all("/[\s|'']#([\w&]+)/i", $pub->contenido, $hashtags);
			if (!empty($hashtags[1])) {
				foreach ($hashtags[1] as $hashtag) {
					$etiquetas = _array::sumaUnoSiExiste($etiquetas, $hashtag);
				}
			}
		}
		ksort($etiquetas, SORT_NATURAL | SORT_FLAG_CASE);
		return $etiquetas;
	}

	#
	public function fechas($usuarios_idu)
	{
		$sql = "SELECT * FROM publicaciones WHERE usuarios_idu=?";
		$publicaciones = self::all($sql, [$usuarios_idu]);
		$fechas = [];
		foreach ($publicaciones as $pub) {
			$anyo = _date::format($pub->publicado, 'Y');
			$anyo_mes = _date::format($pub->publicado, 'Y/m');
			$fechas[$anyo]['veces'] = empty($fechas[$anyo]['veces']) ? 1 : ++$fechas[$anyo]['veces'];
			$fechas[$anyo]['meses'][$anyo_mes] = empty($fechas[$anyo]['meses'][$anyo_mes]) ? 1 : ++$fechas[$anyo]['meses'][$anyo_mes];
			krsort($fechas[$anyo]['meses']);
		}
		krsort($fechas);
		return $fechas;
	}

	# Revisar valores
	public function nuevas()
	{
		/* ⮞ Fix: si no hay timestamp en sesión, devolver 0.
		 * Antes: $fecha[0] = Session::get('ultimas_publicaciones'); luego if (!$fecha) no detectaba null.
		 * Ahora: control explícito, y construir array de parámetros solo si existe valor.
		 */
		$ts = Session::get('ultimas_publicaciones');
		if (empty($ts)) {
			return 0;
		}

		$sql = "SELECT id FROM publicaciones_view WHERE publicado>?";

		$filtro = $this->filtroPorEtiquetas();
		$sql .= $filtro['sql'];

		$anclado = date('Y-m-d H:i:s', strtotime('-24 hours'));
		$sql .= " ORDER BY anclado>'$anclado' DESC, publicado DESC LIMIT 0,50";

		$values = array_merge([$ts], $filtro['vals']);

		$publicaciones = self::all($sql, $values);

		return count($publicaciones);
	}

	#
	public function perfilesSiguiendo()
	{
		$sql = "SELECT pub.*, usu.apodo, usu.hashtag, usu.avatar, usu.email, usu.rol, usu.socio, usu.ultima_pub
			FROM usuarios usu, publicaciones pub
				JOIN acciones acc ON pub.usuarios_idu=acc.idu
			WHERE pub.usuarios_idu=usu.idu
				AND acc.elemento='usuarios'
				AND acc.accion='notificar'
				AND acc.usuarios_idu=?
			ORDER BY pub.publicado DESC LIMIT 50";

		return self::all($sql, [Session::get('idu')]);
	}

	#
	public function porEtiqueta($usuario, $etiqueta)
	{
		$sql = "SELECT pub.*, usu.apodo, usu.hashtag, usu.avatar, usu.email, usu.rol, usu.socio, usu.ultima_pub FROM publicaciones pub, usuarios usu WHERE pub.usuarios_idu=usu.idu AND pub.usuarios_idu=? AND (pub.idioma=? OR pub.tipo=? OR pub.subtipo=? OR pub.contenido LIKE ?) ORDER BY pub.publicado DESC LIMIT 75";
		return self::all($sql, [$usuario->idu, $etiqueta, $etiqueta, $etiqueta, "%#$etiqueta%"]);
	}

	#
	public function porEtiquetas($etiquetas)
	{
		$sql = "SELECT pub.*, usu.apodo, usu.hashtag, usu.avatar, usu.email, usu.rol, usu.socio, usu.ultima_pub FROM publicaciones pub, usuarios usu WHERE pub.usuarios_idu=usu.idu AND (pub.etiquetas LIKE ? OR pub.contenido LIKE ?) ORDER BY pub.publicado DESC LIMIT 75";
		return self::all($sql, ["%$etiquetas%", "%#$etiquetas%"]);
	}

	#
	public function porFecha($usuario, $anyo, $mes='')
	{
		$sql = "SELECT pub.*, usu.apodo, usu.hashtag, usu.avatar, usu.email, usu.rol, usu.socio, usu.ultima_pub FROM publicaciones pub, usuarios usu WHERE pub.usuarios_idu=usu.idu AND pub.usuarios_idu=? AND pub.publicado LIKE ? ORDER BY pub.publicado DESC LIMIT 75";

		$fecha = $mes ? "$anyo-$mes" : $anyo;

		return self::all($sql, [$usuario->idu, "$fecha%"]);
	}

	#
	public function semanaAnterior()
	{
		$sql = "SELECT pub.*, usu.apodo, usu.idu AS usu_idu FROM publicaciones pub, usuarios usu WHERE pub.usuarios_idu=usu.idu AND pub.publicado > ? ORDER BY pub.publicado";

		$desde = date('Y-m-d H:i:s', strtotime('-1 week'));

		return parent::all($sql, [$desde]) ?: [];
	}

	#
	public function sinComentarios($idu)
	{
		$sql = "UPDATE publicaciones SET comentarios=0, fragmentos='' WHERE idu=?";
		self::query($sql, [$idu]);
	}

	# select ... (comentario documental del SQL histórico)
	public function todas($pagina=1)
	{
		$sql = "SELECT * FROM publicaciones_view WHERE id IS NOT NULL";

		$filtro = $this->filtroPorEtiquetas();

		$sql .= $filtro['sql'];

		$anclado = date('Y-m-d H:i:s', strtotime('-24 hours'));
		$sql .= " ORDER BY anclado>'$anclado' DESC, publicado DESC";

		/* ⮞ Paginación segura (offset nunca negativo) */
		$pagina = (int)$pagina;
		if ($pagina < 1) $pagina = 1;

		$sql .= " LIMIT " . ($pagina - 1) * 50 . ',50';

		$publicaciones = self::all($sql, $filtro['vals'] ?? []);

		return self::arrayBy($publicaciones);
	}

	#
	public function todasPorUsuario($usuarios_idu='')
	{
		if (!$usuarios_idu) {
			$usuarios_idu = Session::get('idu');
		}
		$sql = 'SELECT pub.*, usu.apodo, usu.hashtag, usu.avatar, usu.email, usu.rol, usu.socio, usu.ultima_pub FROM publicaciones pub, usuarios usu WHERE pub.usuarios_idu=usu.idu AND pub.usuarios_idu=? ORDER BY publicado DESC LIMIT 75';
		$publicaciones = self::all($sql, [$usuarios_idu]);
		return self::arrayBy($publicaciones);
	}

	#
	public function una($str)
	{
		$sql = 'SELECT pub.*, usu.apodo, usu.hashtag, usu.avatar, usu.email, usu.rol, usu.socio, usu.ultima_pub FROM publicaciones pub, usuarios usu WHERE pub.usuarios_idu=usu.idu AND (pub.idu=? OR pub.slug=?)';
		$una = self::first($sql, [$str, $str]);
		if ($una) {
			$una->anclado = ($una->anclado >= date('Y-m-d H:i:s', strtotime('-1 day'))) ? 1 : 0;
			return $una;
		}
		return self::cols();
	}
}
