<?php
/**
 */
class Rss_entradas extends LiteRecord
{
	# 1
	public function cargarSitios()
	{
		_var::flush("<h1>Cargando el Lector RSS...</h1>");
		_mail::send('dj@roleplus.app', 'Cargando el Lector RSS.', 'Enjoy!');
		$sitios = (new Rss_sitios)->obtenerSitios();
		foreach ($sitios as $sit) {
			$i = $this->cargarSitio($sit->idu, $sit->url, $sit->idioma) ?? 0;
			$body[] = $log = "$sit->url: $i entrada/s nueva/s.";
			_var::flush("<hr>$log");
		}
		_mail::send('dj@roleplus.app', 'Lector RSS cargado.', '<pre>'.print_r($body, 1));
		exit;
	}

	# 1.2
	public function cargarSitio($rss_sitios_idu, $url, $idioma='ES')
	{
		#_var::flush("<h2>($rss_sitios_idu) $url</h2>");

		# BORRADO ANTES DE LA CARGA
		$sql = 'DELETE FROM rss_entradas WHERE rss_sitios_idu=?';
		self::query($sql, [$rss_sitios_idu]);

		# XML EN TEXTO
		$texto = _link::curl_get_file_contents($url);
		#_var::die($texto, 1);
		$texto = str_ireplace(['<![CDATA[', ']]>'], '', $texto);

		# ENTRADA
		$entradas = '';
		if (stristr($texto, '<li class="regularitem"')) {
			$entradas = explode('<li class="regularitem"', $texto);
		}
		elseif (stristr($texto, '<item')) {
			$entradas = explode('<item', $texto);
		}
		elseif (stristr($texto, '<entry')) {
			$entradas = explode('<entry', $texto);
		}

		if ( ! is_array($entradas)) {
			Session::setArray('toast', t('No se han obtenido entradas.'));
			return;
		}

		# CABECERA
		$cabecera = array_shift($entradas);

		$titulo = strip_tags(_str::cut2('<title', '>', $cabecera, '</title>'));
		$titulo = str_ireplace(' - powered by FeedBurner', '', $titulo);
		$titulo = trim($titulo, chr(0xC2).chr(0xA0));
		$subtitulo = stristr($cabecera, '<subtitle')
			? strip_tags(_str::cut2('<subtitle', '>', $cabecera, '</subtitle>'))
			: '';
		$subtitulo = trim($subtitulo);
		if ($titulo && $subtitulo) {
			$sql = 'UPDATE rss_sitios SET nombre=?, descripcion=? WHERE idu=?';
			self::query($sql, [$titulo, $subtitulo, $rss_sitios_idu]);
		}
		else if ($titulo) {
			$sql = 'UPDATE rss_sitios SET nombre=? WHERE idu=?';
			self::query($sql, [$titulo, $rss_sitios_idu]);
		}

		$i = 0;
		foreach ($entradas as $ent) {
			#_var::die($ent, 1);

            $keys[] = '(?, ?, ?, ?, ?, ?, ?, ?)';
			$values[] = $rss_sitios_idu;
			$values[] = $idioma;

			# TÍTULO
			$titulo = 'SIN TÍTULO';
			if (stristr($ent, '<h4 class="itemtitle">')) {
				$titulo = _str::cut2('<h4 class="itemtitle">', '>', $ent, '</a>');
			}
			elseif (stristr($ent, '<title')) {
				$titulo = _str::cut2('<title', '>', $ent, '</title>');
			}
			$values[] = $titulo = html_entity_decode($titulo);

			# IDU
			$values[] = _str::uid($titulo);

			# CONTENIDO
			if (stristr($ent, '<div class="itemcontent"')) {
				$texto = '<div>' . _str::cut2('<li class="itemcontent"', '>', $ent, '</li>');
			}
			elseif (stristr($ent, '<description')) {
				$texto = _str::cut2('<description', '>', $ent, '</description>');
			}
			elseif (stristr($ent, '<summary')) {
				$texto = _str::cut2('<summary', '>', $ent, '</summary>');
			}
			elseif (stristr($ent, '<media:description')) {
				$texto = _str::cut2('<media:description', '>', $ent, '</media:description>');
			}
			else {
				$texto = _str::cut2('<content', '>', $ent, '</content>');
			}
			$values[] = $texto = _html::summary($texto);

			# FOTO
			if (stristr($ent, '<content')) {
				$contenido = _str::cut2('<content', '>', $ent, '</content>');
			}
			else {
				$contenido = $texto;
			}
			$contenido = htmlspecialchars_decode($contenido, ENT_QUOTES);

			$src = '';
			if (stristr($contenido, 'src="')) {
				$src = _str::cut2('<img', 'src="', $contenido, '"');
			}
			elseif (stristr($contenido, "src='")) {
				$src = _str::cut2('<img', "src='", $contenido, "'");
			}
			elseif (stristr($ent, '<media:thumbnail')) {
				$src = _str::cut2('<media:thumbnail', 'url="', $ent, '"');
			}
			# No aceptamos emojis o URLs que generan imágenes
			if (preg_match('/emoji|icon/i', $src) or stristr($src, 'pbs.twimg.com')) {
				$src = '';
			}
			
			$foto = '';
			if ($src) {
				$foto = _link::curl_put_file_contents($src, "img/rss/$rss_sitios_idu/" . _url::slug($titulo));
				_var::echo($foto);
			}
			$values[] = $foto;

			# URL
			if (stristr($ent, '<h4 class="itemtitle">')) {
				$values[] = $enlace = _str::cut2('<h4 class="itemtitle">', 'href="', $ent, '"');
			}
			elseif (stristr($ent, '<link')) {
				if (stristr($ent, '<link rel="alternate"')) {
					$values[] = $enlace = _str::cut2('<link rel="alternate"', 'href="', $ent, '"');
				}
				elseif (stristr($ent, "<link rel='alternate'")) {
					$values[] = $enlace = _str::cut2("<link rel='alternate'", "href='", $ent, "'");
				}
				elseif (stristr($ent, '<link>')) {
					$values[] = $enlace = _str::cut('<link>', $ent, '<');
				}
				elseif (stristr($ent, 'href="')) {
					$values[] = $enlace = _str::cut2('<link', 'href="', $ent, '"');
				}
				elseif (stristr($ent, "href='")) {
					$values[] = $enlace = _str::cut2('<link', "href='", $ent, "'");
				}
				else {
					$values[] = $enlace = _str::cut2('<link', '>', $ent, '</link>');
				}
			}
			else {
				Session::setArray('toast', t('Enlace no hallado.'));
				continue;
			}

			# PUBLICADO
			$publicado = '';
			if (stristr($ent, '<h5 class="itemposttime">')) {
				$publicado = _str::cut2('<h5 class="itemposttime">', '<span>Posted:</span>', $ent, '</h5>');
			}
			elseif (stristr($ent, '<pubDate')) {
				$publicado = _str::cut2('<pubDate', '>', $ent, '</pubDate>');
			}
			elseif (stristr($ent, '<published')) {
				$publicado = _str::cut2('<published', '>', $ent, '</published>');
			}
			$values[] = $publicado = date('Y-m-d H:i:s', strtotime($publicado));

			++$i;			
			if ($i == 10) {
				break;
			}
		}

		#_var::flush($values, 1);

		if (empty($values)) {
			Session::setArray('toast', t('No se han obtenido entradas.'));
			return;
		}
		$sql = 'INSERT INTO rss_entradas (rss_sitios_idu, idioma, titulo, idu, contenido, fotos, url, publicado) VALUES ' . implode(', ', $keys);
		self::query($sql, $values);

		Session::setArray('toast', t('Entradas nuevas: ') . $i);
		return $i;
	}

	#
	public function parchear() {
		$sql = 'SELECT id, fotos FROM rss_entradas';
		$entradas = self::all($sql);
		foreach ($entradas as $ent) {
			$sql = 'UPDATE rss_entradas SET fotos=? WHERE id=?';
			$fotos = unserialize($ent->fotos);
			if (empty($fotos[0])) {
				continue;
			}
			self::query($sql, [$fotos[0], $ent->id]);
		}
	}

	#
	public function obtenerEntradas()
	{
		$suscripciones = (new Rss_suscripciones)->obtenerSuscripciones();
		if ( ! $suscripciones) {
			$sql = "SELECT * FROM rss_entradas ORDER BY publicado DESC LIMIT 50";
			$entradas = self::all($sql);
		}
		else {	
			$keys = $values = [];
			foreach ($suscripciones as $sus) {
				$keys[] = '?';
				$values[] = $sus->rss_sitios_idu;
			}
			$in = implode(', ', $keys);
			/*if (is_array($in)) {
				return [];
			}*/
			$sql = "SELECT * FROM rss_entradas WHERE rss_sitios_idu NOT IN ($in) ORDER BY publicado DESC LIMIT 50";
			$entradas = self::all($sql, $values);
		}

		foreach ($entradas as $ent) {
			$entradas_por_titulo[$ent->titulo] = $ent;
		}

		$keys = $values = [];
		foreach ($entradas as $ent) {
			$keys[] = '?';
			$values[] = $ent->titulo;
		}
		$in = implode(', ', $keys);
		if (empty($in)) {
			return [];
		}

		$sql = "SELECT titulo FROM publicaciones WHERE titulo IN ($in)";
		$entradas_publicadas = self::all($sql, $values);
		#_var::die([$entradas_publicadas]);
		foreach ($entradas_publicadas as $ent) {
			$entradas_publicadas_por_titulo[$ent->titulo] = $ent;
		}

		foreach ($entradas_por_titulo as $titulo=>$ent) {
			$ent->entrada_publicada = empty($entradas_publicadas_por_titulo[$titulo]) ? 0 : 1;
		}
		return $entradas_por_titulo;
	}

	#
	public function prepararEntrada($idu)
	{
		$sql = 'SELECT * FROM rss_entradas WHERE idu=?';
		$ent = self::first($sql, [$idu]);
		$entrada['titulo'] = $ent->titulo;
		$entrada['idioma'] = $ent->idioma;
		$entrada['etiquetas'] = _str::hashtag(_cut::cut('//', str_replace('www.', '', $ent->url), '.'));

        $entrada['contenido'] = (new Respuestas)->preguntarAOpenAi("Resumen y traduce si es necesario sin incluir enlaces ni otras distracciones que no sean emojis del siguiente texto (hazlo comprensible, enriquecedor y evocativo y siempre dirigiendote al lector en segunda persona): " . h($ent->contenido) . " [$ent->url]");

		#$entrada['enlace'] = stristr($ent->url, 'ivoox') ? $ent->url : '';
		$entrada['enlace'] = $ent->url;

		$entrada['usuarios_idu'] = $idu = Session::get('idu');
		if ($ent->fotos) {
			copy("img/rss/$ent->rss_sitios_idu/$ent->fotos", "img/usuarios/$idu/$ent->fotos");
			$entrada['fotos'][] = $ent->fotos;
		}
		#_var::die($entrada);
		return $entrada;
	}

	#
	public function publicarEntrada($idu)
	{
		$entrada = $this->prepararEntrada($idu);
		(new Publicaciones)->crear($entrada);
	}

	#
	public function quitarEntradas($rss_sitios_idu)
	{
		$sql = 'DELETE FROM rss_entradas WHERE rss_sitios_idu=?';
		self::query($sql, [$rss_sitios_idu]);
	}
}
