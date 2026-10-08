<?php
/**
 */
class Rolflix_sitios extends LiteRecord
{
	# 0
	/*public function arreglo()
	{
		$sitios = self::obtenerSitios();
		foreach ($sitios as $sit) {
			$html = _link::curl_get_file_contents($sit->canal);
			$usuario = _str::cut('"ownerUrls":["http://www.youtube.com/', $html, '"');
			$usuario = urldecode($usuario);
			$hashtag = _str::hashtag(str_replace(['@', 'c/'], '', $usuario));
			$canal_id = _str::cut('https://www.youtube.com/feeds/videos.xml?channel_id=', $html, '"');
			$sql = 'UPDATE rolflix_sitios SET hashtag=?, usuario=?, canal_id=? WHERE id=?';
			$r = parent::query($sql, [$hashtag, $usuario, $canal_id, $sit->id]);
			_var::flush([$sql, $hashtag, $usuario, $canal_id, $sit->id, $r], 1);
		}
	}*/

	#
	public function incluirSitio($dat)
	{
		# URL DEL CANAL
		# https://www.youtube.com/@EnekoMenica
		# https://www.youtube.com/c/VíctorRomero
		$url = trim($dat['url'] ?? '');
		$parts = parse_url($url);
		if ( ! $parts || ! in_array(strtolower($parts['host'] ?? ''), ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)
			|| ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
			|| ! empty($parts['user']) || ! empty($parts['pass']) || ! empty($parts['port'])) {
			Session::setArray('toast', t('Introduce el enlace de un canal de YouTube.'));
			return false;
		}
		$html = _link::curl_get_file_contents($url);
		$canal_id = _str::cut('https://www.youtube.com/feeds/videos.xml?channel_id=', $html, '"');
		if ( ! preg_match('/^UC[A-Za-z0-9_-]{22}$/', (string) $canal_id)) {
			Session::setArray('toast', t('No se pudo identificar el canal de YouTube. Inténtalo más tarde.'));
			return false;
		}
		$usuario = _str::cut('"ownerUrls":["http://www.youtube.com/', $html, '"');
		$usuario = str_replace(['@', 'c/'], '', $usuario);
		if ( ! $usuario) {
			$usuario = trim($parts['path'] ?? '', '/');
			$usuario = preg_replace('~^(?:@|c/|user/|channel/)~', '', $usuario);
		}
		#$usuario = urldecode($usuario);
		#_var::die($hashtag);
		$hashtag = _str::hashtag($usuario);
		/*$canal = 'https://www.youtube.com/channel/' . $canal_id;
		$idu = _::id($canal);*/
		$idu = _str::id($canal_id);
		$sql = 'SELECT id FROM rolflix_sitios WHERE canal_id=?';
		$hay = self::first($sql, [$canal_id]);
		if ($hay) {
			Session::setArray('toast', t('Ese canal ya lo tenemos.'));
			return false;
		}
		$values[] = $idu;
		$values[] = Session::get('idu');
		$values[] = $usuario;
		$values[] = $hashtag;
		$values[] = $canal_id;
		$sql = 'INSERT INTO rolflix_sitios SET idu=?, usuarios_idu=?, usuario=?, hashtag=?, canal_id=?';
		self::query($sql, $values);
		try {
			$cargados = (new Rolflix_entradas)->cargarSitio($idu, $canal_id);
			if ($cargados === false) {
				Session::setArray('toast', t('Canal añadido. Los vídeos se cargarán en la próxima actualización.'));
			}
		} catch (Throwable $e) {
			error_log("Fallo carga inicial de YouTube [$canal_id]: " . $e->getMessage());
			Session::setArray('toast', t('Canal añadido. Los vídeos se cargarán en la próxima actualización.'));
		}
		return true;
	}

	#
	public function obtenerSitios()
	{
		$sql = 'SELECT * FROM rolflix_sitios ORDER BY hashtag DESC';
		return self::all($sql);
	}

	#
	public function quitarSitio($idu)
	{
		$sql = 'SELECT id FROM rolflix_sitios WHERE idu=? AND usuarios_idu=?';
		$hay = self::first($sql, [$idu, Session::get('idu')]);
		if (Session::get('rol') > 5 || $hay) {
			$sql = 'DELETE FROM rolflix_sitios WHERE idu=?';
			self::query($sql, [$idu]);
			(new Rolflix_entradas)->quitarVideos($idu);
		}
	}
}
