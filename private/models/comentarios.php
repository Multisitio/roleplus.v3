<?php
/**
 */
class Comentarios extends LiteRecord
{
	#
	public function contar($publicaciones_idu)
	{
		$sql = 'SELECT COUNT(*) c FROM comentarios WHERE publicaciones_idu=? AND (borrado=0 OR borrado IS NULL)';
		$row = self::first($sql, [$publicaciones_idu]);
		return (int)($row ? $row->c : 0);
	}

	#
	public function crear($arr)
	{
		if (empty($arr['comentario'])) {
			return Session::setArray('toast', t('No se permiten comentarios vacíos de texto.'));
		}

		_mail::toAdmin('Comentario en R+', '<pre>' . print_r($arr, 1));

		$eventos_idu = $arr['eventos_idu'] ?? '';
		$publicaciones_idu = $arr['publicaciones_idu'] ?? '';

		$comentario = $arr['comentario'];
		$comentario_formateado = $this->formatear($comentario);
		$idu = _str::uid($comentario);

		$pub = null;
		if ($publicaciones_idu) {
			$pub = (new Publicaciones)->una($publicaciones_idu);
			$hay = (new Acciones)->registro($publicaciones_idu, 'publicaciones', 'notificar');
			if ( ! $hay) {
				(new Acciones)->crear('publicaciones', $publicaciones_idu, 'notificar');
			}
		}

		$enlace = '';
		if ( ! empty($arr['preview'])) {
			if (stristr($arr['preview']['url'], 'youtu')) {
				$enlace = $arr['preview']['url'];
			} else {
				(new Vistas_previas)->guardarVistas($idu, $arr['preview']);
			}
		} elseif ( ! empty($arr['enlace'])) {
			$enlace = $arr['enlace'];
		}

		$fotos = $arr['fotos'] ?? [];
		if ( ! is_array($fotos)) {
			$fotos = array_filter(array_map('trim', explode(',', $fotos)));
		}

		$nuevas = [];
		if (isset($_FILES['imagenes'])) {
			$f = $_FILES['imagenes'];
			$hay = is_array($f['name'])
				? array_reduce(array_keys($f['name']), function ($c, $i) use ($f) { return $c || ($f['name'][$i] !== '' && isset($f['error'][$i]) && $f['error'][$i] === UPLOAD_ERR_OK); }, false)
				: ($f['name'] !== '' && isset($f['error']) && $f['error'] === UPLOAD_ERR_OK);
			if ($hay) {
				$n = (new Archivos)->incluir($_FILES);
				$nuevas = is_array($n) ? $n : ($n ? [$n] : []);
			}
		}
		if ($nuevas) {
			$fotos = array_merge($fotos, $nuevas);
		}
		$fotos_str = implode(',', $fotos);

		$sql = 'INSERT INTO comentarios SET usuarios_idu=?, usuarios_hashtag=?, eventos_idu=?, publicaciones_idu=?, comentario=?, comentario_formateado=?, enlace=?, idu=?, fotos=?, publicado=?';
		$usuarios_hashtag = _str::hashtag(Session::get('apodo'));
		self::query($sql, [Session::get('idu'), $usuarios_hashtag, $eventos_idu, $publicaciones_idu, $comentario, $comentario_formateado, $enlace, $idu, $fotos_str, _date::format()]);

		$com = $this->uno($idu);
		$comentario_plano = strip_tags($comentario_formateado);

		if ($eventos_idu) {
			(new Notificaciones)->comentandoEvento('comentando', 'eventos', $eventos_idu, $comentario_plano);
			(new Eventos)->comentario($eventos_idu);
		} elseif ($publicaciones_idu && $pub) {
			(new Notificaciones)->comentando($pub, $com);
			(new Publicaciones)->comentario($publicaciones_idu);
			(new Publicaciones)->agregaComentarios($publicaciones_idu);
		}
	}

	#
	public function eliminarEnlace($comentarios_idu)
	{
		$sql = 'UPDATE comentarios SET enlace="" WHERE usuarios_idu=? AND idu=?';
		self::query($sql, [Session::get('idu'), $comentarios_idu]);
		Session::setArray('toast', 'Enlace eliminado.');
	}

	#
	public function editar($arr)
	{
		$imagenes = '';
		if ( ! empty($arr['imagenes'])) {
			$imagenes = is_array($arr['imagenes']) ? implode(',', array_filter(array_map('trim', $arr['imagenes']))) : (string)$arr['imagenes'];
			$imagenes = $imagenes === '0' ? '' : $imagenes; // no almacenar escapado; escapar en vistas
		}

		if (isset($_FILES['imagenes'])) {
			$f = $_FILES['imagenes'];
			$hay = is_array($f['name'])
				? array_reduce(array_keys($f['name']), function ($c, $i) use ($f) { return $c || ($f['name'][$i] !== '' && isset($f['error'][$i]) && $f['error'][$i] === UPLOAD_ERR_OK); }, false)
				: ($f['name'] !== '' && isset($f['error']) && $f['error'] === UPLOAD_ERR_OK);
			if ($hay) {
				$n = (new Archivos)->incluir($_FILES);
				$imagenes = is_array($n) ? implode(',', $n) : (string)$n;
			}
		}

		$sql = 'UPDATE comentarios SET comentario=?, comentario_formateado=?, fotos=? WHERE usuarios_idu=? AND idu=?';
		self::query($sql, [$arr['comentario'], $this->formatear($arr['comentario']), $imagenes, Session::get('idu'), $arr['idu']]);

		if ( ! empty($arr['preview'])) {
			(new Vistas_previas)->guardarVistas($arr['idu'], $arr['preview']);
		}

		Session::setArray('toast', 'Comentario editado.');
	}

	#
	public function eliminar($idu)
	{
		$com = $this->uno($idu);
		$sql = 'DELETE FROM comentarios WHERE usuarios_idu=? AND idu=?';
		self::query($sql, [Session::get('idu'), $idu]);
		Session::setArray('toast', '¡Comentario eliminado!');
		(new Publicaciones)->comentario($com->publicaciones_idu);
		(new Publicaciones)->agregaComentarios($com->publicaciones_idu);
	}

	#
	public function formatearComentariosSinformatear($confirmar)
	{
		$sql = 'SELECT id FROM comentarios WHERE (comentario IS NULL OR comentario="") AND (comentario_formateado IS NULL OR comentario_formateado="")';
		$comentarios_vacios = self::all($sql);
		echo count($comentarios_vacios) . ' comentarios vacios a borrar.<br>';

		$sql = 'DELETE FROM comentarios WHERE (comentario IS NULL OR comentario="") AND (comentario_formateado IS NULL OR comentario_formateado="")';
		self::query($sql);

		$sql = 'SELECT id FROM comentarios WHERE (comentario IS NULL OR comentario="") AND (comentario_formateado IS NULL OR comentario_formateado="")';
		$comentarios_vacios = self::all($sql);
		echo count($comentarios_vacios) . ' comentarios vacios han quedado.<br>';

		$sql = 'SELECT id, comentario, comentario_formateado FROM comentarios WHERE comentario IS NOT NULL AND (comentario_formateado IS NULL OR comentario_formateado="")';
		$comentarios = self::all($sql);

		foreach ($comentarios as $com) {
			echo '<hr>' . $sql = "UPDATE comentarios SET comentario_formateado=? WHERE id=? LIMIT 1";
			_var::echo([$this->formatear($com->comentario), $com->id]);
			echo self::query($sql, [$this->formatear($com->comentario), $com->id]) ? ' >> PERFECTO' : ' >> MAL... :--(';
			_var::flush();
		}
		die;
	}

	#
	public function formatear($comentario)
	{
		$comentario_formateado = $comentario;
		$comentario_formateado = _html::bbcode($comentario_formateado);
		$comentario_formateado = _html::links($comentario_formateado);
		return $comentario_formateado;
	}

	#
	public function sueltos()
	{
		$sql = 'SELECT com.*, pub.titulo, usu.apodo, usu.hashtag, usu.avatar, usu.email, usu.hashtag FROM comentarios com, publicaciones pub, usuarios usu WHERE com.usuarios_idu=usu.idu AND com.publicaciones_idu=pub.idu AND com.borrado IS NULL ORDER BY com.publicado DESC LIMIT 150';
		return self::all($sql);
	}

	#
	public function todos($publicaciones_idu)
	{
		$no_listar = (new Etiquetas_usuarios)->todas('no_listar');
		$vals = [$publicaciones_idu];

		if ($no_listar) {
			$keys = [];
			foreach ($no_listar as $obj) {
				$keys[] = '?';
				$vals[] = $obj->hashtag;
			}
			$keys_str = implode(', ', $keys);

			$sql = "SELECT com.*, usu.apodo, usu.hashtag, usu.avatar, usu.email, usu.hashtag, usu.rol FROM comentarios com, usuarios usu WHERE com.usuarios_idu=usu.idu AND com.publicaciones_idu=? AND com.usuarios_hashtag NOT IN ($keys_str) ORDER BY com.publicado";
		} else {
			$sql = 'SELECT com.*, usu.apodo, usu.hashtag, usu.avatar, usu.email, usu.hashtag, usu.rol FROM comentarios com, usuarios usu WHERE com.usuarios_idu=usu.idu AND com.publicaciones_idu=? ORDER BY com.publicado';
		}
		return self::all($sql, $vals) ?: [];
	}

	#
	public function uno($idu)
	{
		$sql = 'SELECT * FROM comentarios WHERE idu=? AND (borrado=0 OR borrado IS NULL) ORDER BY publicado DESC';
		return self::first($sql, [$idu]);
	}
}
