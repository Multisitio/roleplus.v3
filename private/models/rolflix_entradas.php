<?php
/**
 */
class Rolflix_entradas extends LiteRecord
{
	# 0
	/*public function arreglo()
	{
		$sql = "SELECT * FROM rolflix_sitios";
		$sitios = parent::all($sql);
		$sitios = parent::arrayBy($sitios);
		#_var::die($sitios);

		$sql = "SELECT * FROM rolflix_entradas";
		$entradas = parent::all($sql);
		$c = 0;
		foreach ($entradas as $ent) {
			++$c;
			_var::flush("<hr>$c: ");
			if (empty($sitios[$ent->rolflix_sitios_idu])) {
				_var::flush("Idu ($ent->rolflix_sitios_idu) huérfano... :--(");
				die;
			}
			_var::flush("Idu ($ent->rolflix_sitios_idu) de {$sitios[$ent->rolflix_sitios_idu]->hashtag} ");
		}
	}*/

	# 1
	public function cargarSitios()
	{
		_mail::send('dj@roleplus.app', 'Cargando vídeos de canales vía RSS...', 'Enjoy!');
		$sitios = (new Rolflix_sitios)->obtenerSitios();
		foreach ($sitios as $sit) {
			$i = $this->cargarSitio($sit->idu, $sit->canal_id, $sit);
			$body[] = $log = "$sit->hashtag: $i vídeo/s nuevo/s.";
			_var::flush("<hr>$log");
		}
		_mail::send('dj@roleplus.app', 'Vídeos de canales cargados vía RSS.', '<pre>'.print_r($body, 1));
		exit;
	}

	# 1.1
	public function cargarSitio($rolflix_sitios_idu, $canal_id, $sit=null)
	{
		# Recogemos los enlaces existentes para no duplicar vídeos
		$enlaces = $this->obtenerEnleces();

		# XML EN TEXTO
        $url = "https://www.youtube.com/feeds/videos.xml?channel_id=$canal_id";
		$texto = _link::curl_get_file_contents($url);
		if ( ! $texto) {
			return _mail::toAdmin('Error obteniendo el texto', _var::return($sit));
		}
		$texto = str_ireplace(['<![CDATA[', ']]>'], '', $texto);

		# ENTRADAS
		$videos = explode('<entry>', $texto);

		# CABECERA
		$cabecera = array_shift($videos);

		$i = 0;
		foreach ($videos as $vid) {
			# MIRAMOS SI YA TENEMOS EL VÍDEO
			$enlace = _str::cut2('<link', 'href="', $vid, '"');
			if (in_array($enlace, $enlaces)) {
				continue;
			}

			$titulo = _str::cut2('<title', '>', $vid, '</title>');
			$descripcion = _str::cut2('<media:description', '>', $vid, '</media:description>');
			# MIRAMOS QUE NO SEA UN GAMEPLAY
			if (preg_match('/(gameplay)/i', "$titulo $descripcion")) {
				continue;
			}

			# IDU DEL CANAL
			$values[] = $rolflix_sitios_idu;

			# NOMBRE DEL VÍDEO
			$values[] = $titulo;

			# IDU DEL VÍDEO
			$values[] = _str::uid($titulo);

			# ENLACE DEL VÍDEO
			$values[] = $enlace;

			# DESCRIPCIÓN DEL VÍDEO
			$values[] = $descripcion;

			# ETIQUETAS
			$etiquetas = 'info';
			if (preg_match('/(\/|partida|sesión)/i', "$titulo $descripcion")) {
				$etiquetas = 'partidas';
			}
			elseif (preg_match('/(teoría)/i', "$titulo $descripcion")) {
				$etiquetas = 'teoria';
			}
			elseif (preg_match('/(descaja|reseña|unboxing)/i', "$titulo $descripcion")) {
				$etiquetas = 'resenyas';
			}
			elseif (preg_match('/(kickstarter|mecenazgo|verkami)/i', "$titulo $descripcion")) {
				$etiquetas = 'mecenazgos';
			}
			elseif (preg_match('/(editorial|entrevista)/i', "$titulo $descripcion")) {
				$etiquetas = 'entrevistas';
			}
			elseif (preg_match('/(charla)/i', "$titulo $descripcion")) {
				$etiquetas = 'charlas';
			}
			$values[] = $etiquetas;

			# FECHA DEL VÍDEO
			$fecha = _str::cut2('<published', '>', $vid, '</published>');
			$values[] = date('Y-m-d H:i:s', strtotime($fecha));

            $keys[] = '(?, ?, ?, ?, ?, ?, ?)';

			++$i;
		}
		#_var::die($values);
		Session::setArray('toast', t('Vídeos cargados: ') . $i);

		if (empty($values)) {
			return $i;
		}
		$sql = 'INSERT INTO rolflix_entradas (rolflix_sitios_idu, titulo, idu, enlace, descripcion, etiquetas, publicado) VALUES ' . implode(', ', $keys);
		self::query($sql, $values);
		return $i;
	}

	#
	public function etiquetarVideo($arr)
	{
		if ( ! array_key_exists($arr['etiquetas'], $this->etiquetas())) {
            return Session::setArray('toast', t('Etiqueta no admitida.'));
		}	

		$sql = 'UPDATE rolflix_entradas SET etiquetas=? WHERE idu=?'; 
		self::query($sql, [$arr['etiquetas'], $arr['idu']]);

		return $this->etiquetas($arr['etiquetas']);
	}

	#
	public function etiquetas($clave='')
	{
		$etiquetas = [
			'teoria'=>t('Teoría'),
			'resenyas'=>t('Reseñas'),
			'partidas'=>t('Partidas'),
			'mecenazgos'=>t('Mecenazgos'),
			'info'=>t('Info'),
			'entrevistas'=>t('Entrevistas'),
			'charlas'=>t('Charlas'),
			'otros'=>t('Otros'),
		];		

		if ( ! $clave) {
			return $etiquetas;
		}	

		if ( ! empty($etiquetas[$clave])) {
			return $etiquetas[$clave];
		}
		return $etiquetas['otros'];
	}

	#
	public function obtenerEntradas()
	{
		$suscripciones = (new Rolflix_suscripciones)->obtenerSuscripciones();
		if ( ! $suscripciones) {
			$sql = "SELECT r_e.*, r_s.hashtag FROM rolflix_entradas r_e, rolflix_sitios r_s WHERE r_e.rolflix_sitios_idu=r_s.idu ORDER BY publicado DESC LIMIT 50";
			$entradas = self::all($sql);
		}
		else {
			$keys = $values = [];
			foreach ($suscripciones as $sus) {
				$keys[] = '?';
				$values[] = $sus->rolflix_sitios_idu;
			}
			$in = implode(', ', $keys);
			/*if (is_array($in)) {
				return [];
			}*/
			$sql = "SELECT r_e.*, r_s.hashtag FROM rolflix_entradas r_e, rolflix_sitios r_s WHERE r_e.rolflix_sitios_idu=r_s.idu AND rolflix_sitios_idu NOT IN ($in) ORDER BY publicado DESC LIMIT 50";
			$entradas = self::all($sql, $values);
		}	

		foreach ($entradas as $ent) {
			$entradas_por_titulo[$ent->hashtag] = $ent;
		}

		$keys = $values = [];
		foreach ($entradas as $ent) {
			$keys[] = '?';
			$values[] = $ent->hashtag;
		}
		$in = implode(', ', $keys);
		$sql = "SELECT titulo FROM publicaciones WHERE titulo IN ($in)";
		$entradas_publicadas = self::all($sql, $values);
		#_var::die([$sql, $values, $entradas_publicadas]);
		foreach ($entradas_publicadas as $ent) {
			$entradas_publicadas_por_titulo[$ent->titulo] = $ent;
		}

		foreach ($entradas_por_titulo as $titulo=>$ent) {
			$ent->entrada_publicada = empty($entradas_publicadas_por_titulo[$titulo]) ? 0 : 1;
		}
		return $entradas_por_titulo;
	}

	#
	public function anternarDeMiLista($rolflix_entradas_idu)
	{
		$sql = 'SELECT id FROM rolflix_entradas_usuarios WHERE rolflix_entradas_idu=?';
        $hay = self::first($sql, [$rolflix_entradas_idu]);
		if ($hay) {
			$sql = 'DELETE FROM rolflix_entradas_usuarios WHERE id=?';
			self::query($sql, [$hay->id]);
			return 0;
		}
		$sql = 'INSERT INTO rolflix_entradas_usuarios SET usuarios_idu=?, rolflix_entradas_idu=?';
		self::query($sql, [Session::get('idu'), $rolflix_entradas_idu]);
		return 1;
	}

	#
	public function obtenerEnleces()
	{
		$sql = 'SELECT enlace FROM rolflix_entradas'; 
		$arr = self::all($sql);
		$enlaces = [];
		foreach ($arr as $obj) {
			$enlaces[] = $obj->enlace;
		}
		return $enlaces;
	}

	#
	public function obtenerMiLista()
	{
		$sql = 'SELECT * FROM rolflix_entradas WHERE idu IN (SELECT rolflix_entradas_idu FROM rolflix_entradas_usuarios WHERE usuarios_idu=?) ORDER BY publicado DESC LIMIT 50';
        $arr = self::all($sql, [Session::get('idu')]);
		$mi_lista = [];
		foreach ($arr as $obj) {
			$mi_lista[$obj->idu] = $obj;
		}
		return $mi_lista;
	}

	#
	public function obtenerVideo($idu)
	{
		$sql = 'SELECT * FROM rolflix_entradas WHERE idu=?'; 
		return self::first($sql, [$idu]); 
	}

	#
	public function obtenerVideos($etiqueta='')
	{
		$suscripciones = (new Rolflix_suscripciones)->obtenerSuscripciones();
		$values = [];
		if ($suscripciones) {
			foreach ($suscripciones as $sus) {
				$keys[] = '?';
				$values[] = $sus->rolflix_sitios_idu;
			}
			$in = implode(', ', $keys);
			if (is_array($in)) {
				return [];
			}
			$sql = "SELECT ent.*, sit.hashtag FROM rolflix_entradas ent, rolflix_sitios sit WHERE ent.rolflix_sitios_idu NOT IN ($in)";
		}
		else {
			$sql = "SELECT ent.*, sit.hashtag FROM rolflix_entradas ent, rolflix_sitios sit WHERE ent.rolflix_sitios_idu AND ent.rolflix_sitios_idu=sit.idu";
		}

		if ($etiqueta == 'buscar') {
			$sql .= ' AND (ent.titulo LIKE ? OR ent.descripcion LIKE ? OR ent.etiquetas LIKE ?)';
			$values[] = '%'.$_GET['q'].'%';
			$values[] = '%'.$_GET['q'].'%';
			$values[] = '%'.$_GET['q'].'%';
		}
		elseif ($etiqueta) {
			$sql .= ' AND ent.etiquetas=?';
			$values[] = $etiqueta;
		} 
		$sql .= ' AND ent.rolflix_sitios_idu=sit.idu ORDER BY ent.publicado DESC LIMIT 50';

		return self::all($sql, $values);
	}

	#
	public function prepararEntrada($idu)
	{
		$sql = 'SELECT r_e.*, r_s.hashtag FROM rolflix_entradas r_e, rolflix_sitios r_s  WHERE r_e.rolflix_sitios_idu=r_s.idu AND r_e.idu=?';
		$ent = self::first($sql, [$idu]);
		$entrada['titulo'] = $ent->titulo;

        $entrada['contenido'] = (new Respuestas)->preguntarAOpenAi("Resumen y traduce si es necesario sin incluir enlaces ni otras distracciones que no sean emojis del siguiente texto (hazlo comprensible, enriquecedor y evocativo y siempre dirigiendote al lector en segunda persona): " . h($ent->descripcion));

		$entrada['idioma'] = 'ES';
		$entrada['etiquetas'] = $ent->hashtag;
		$entrada['enlace'] = $ent->enlace;
		$entrada['usuarios_idu'] = $usuarios_idu = Session::get('idu');
		if ($ent->fotos) {
			copy("img/rss/$ent->rss_sitios_idu/$ent->fotos", "img/usuarios/$usuarios_idu/$ent->fotos");
			$entrada['fotos'][] = $ent->fotos;
		}
		return $entrada;
	}

	#
	public function publicarEntrada($idu)
	{
		$entrada = $this->prepararEntrada($idu);
		(new Publicaciones)->crear($entrada);
	}

	#
	public function quitarVideos($rolflix_sitios_idu)
	{
		$sql = 'DELETE FROM rolflix_entradas WHERE rolflix_sitios_idu=?';
		self::query($sql, [$rolflix_sitios_idu]);
	}
}
