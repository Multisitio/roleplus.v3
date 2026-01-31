<?php
/**
 */
class Rss_sitios extends LiteRecord
{
	#
	public function incluirSitio($post)
	{
		$url = preg_match('/blogspot|blogger/i', $post['url'])
			? str_ireplace('http://', 'https://', $post['url'])
			: $post['url'];
		$url = trim($url);
		$sql = 'SELECT id FROM rss_sitios WHERE url=?';
		$hay = self::first($sql, [$url]);

		if ($hay) {
			return Session::setArray('toast', t('La URL proporcionada ya existe.'));
		}

		$idioma = mb_strtoupper($post['idioma']);
		$idiomas = Config::get('combos.idiomas');

		if (empty($idiomas[$idioma])) {
			$idioma = 'ES';
		}

		$values[] = Session::get('idu');
		$values[] = $idioma;
		$values[] = $idu = _str::id($url);
		$values[] = $url;

		$sql = 'INSERT INTO rss_sitios SET usuarios_idu=?, idioma=?, idu=?, url=?';
		self::query($sql, $values);

		$entradas = (new Rss_entradas)->cargarSitio($idu, $url, $idioma);
		if ( ! $entradas) {
			$sql = 'DELETE FROM rss_sitios WHERE idu=?';
			self::query($sql, [$idu]);
		}
	}

	#
	public function obtenerSitios()
	{
		$sql = 'SELECT * FROM rss_sitios ORDER BY nombre DESC';
		return self::all($sql);
	}

	#
	public function obtenerSitio($idu)
	{
		$sql = 'SELECT * FROM rss_sitios WHERE idu=?';
		return self::first($sql, [$idu]);
	}

	#
	public function quitarSitio($idu)
	{
		$sql = 'SELECT id FROM rss_sitios WHERE usuarios_idu=? AND idu=?';
		$hay = self::first($sql, [Session::get('idu'), $idu]);
		if (Session::get('rol') > 5 or $hay) {
			$sql = 'DELETE FROM rss_sitios WHERE idu=?';
			self::query($sql, [$idu]);
			(new Rss_entradas)->quitarEntradas($idu);
			(new Rss_suscripciones)->quitarSuscripciones($idu);

			/*$dir = (preg_match('/localhost|roleplus\.vh/', $_SERVER['HTTP_HOST']))
				? rtrim($_SERVER['DOCUMENT_ROOT'], '/')
				: '/hd2';*/
			$dir = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
			if (realpath("$dir/img/rss/$idu")) {
				array_map('unlink', glob("$dir/img/rss/$idu/*"));
				rmdir("$dir/img/rss/$idu");
			}
		}
	}
}
