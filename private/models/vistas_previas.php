<?php
/**
 */
class Vistas_previas extends LiteRecord
{
	private const IMAGE_URL_DIR = '/img/vistas_previas';

	# 1
	public function guardarVistas($donde_idu, $preview)
	{
		if (empty($donde_idu) || !is_array($preview)) {
			return;
		}

		$idu = _str::uid();
		$image = '';

		if (!empty($preview['image'])) {
			$src = trim((string)$preview['image']);
			$ext = _img::getExt($src);

			if ($ext) {
				$docroot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
				if ($docroot === '') {
					$docroot = rtrim(PUB_PATH, '/\\');
				}

				$dir = $docroot . self::IMAGE_URL_DIR . '/' . $idu . '/';
				if (!is_dir($dir)) {
					mkdir($dir, 0775, true);
				}

				$name = $idu . '.' . $ext;
				$path = $dir . $name;

				$res = _link::curl_put_file_contents($src, $path);
				if ($res && file_exists($path)) {
					$image = $name;	// solo nombre + extensión en BD
				}
			}
		}

		self::eliminarVistasPrevias($donde_idu);

		$sql = 'INSERT INTO vistas_previas SET
			usuarios_idu=?, donde_idu=?, url=?, title=?, idu=?, description=?, image=?';

		$vals = [
			Session::get('idu'),
			$donde_idu,
			_html::stripTags($preview['url'] ?? ''),
			_html::stripTags($preview['title'] ?? ''),
			$idu,
			_html::stripTags($preview['description'] ?? ''),
			$image,
		];

		self::query($sql, $vals);
	}

	# 1.2
	public function eliminarVistasPrevias($donde_idu)
	{
		if (empty($donde_idu)) {
			return;
		}

		$sql = 'DELETE FROM vistas_previas WHERE usuarios_idu=? AND donde_idu=?';
		self::query($sql, [Session::get('idu'), $donde_idu]);
	}

	# 2
	public function eliminarVista($idu)
	{
		$una = $this->una($idu);
		if (empty($una->id)) {
			Session::setArray('toast', t('Vista previa no encontrada.'));
			return;
		}

		$donde = (new Publicaciones)->una($una->donde_idu);
		if (empty($donde->id)) {
			$donde = (new Comentarios)->uno($una->donde_idu);
		}
		if (empty($donde->id)) {
			Session::setArray('toast', t('Publicación no encontrada.'));
			return;
		}

		if (Session::get('idu') <> $donde->usuarios_idu) {
			Session::setArray('toast', t('¡Atrás Satanás!'));
			return;
		}

		$sql = 'DELETE FROM vistas_previas WHERE donde_idu=? LIMIT 1';
		self::query($sql, [$una->donde_idu]);
		Session::setArray('toast', t('Vista previa eliminada.'));
	}

	# 2.1
	public function una($idu)
	{
		$sql = 'SELECT * FROM vistas_previas WHERE idu=?';
		return self::first($sql, [$idu]);
	}

	#
	public function obtenerTodas($sitios)
	{
		$keys = [];
		$vals = [];

		foreach ($sitios as $sit) {
			$keys[] = '?';
			$vals[] = $sit->idu;
		}

		if (empty($keys)) {
			return [];
		}

		$sql = 'SELECT * FROM vistas_previas WHERE donde_idu IN (' . implode(', ', $keys) . ')';
		$arr = self::all($sql, $vals);

		$vistas = [];
		foreach ($arr as $obj) {
			if (!isset($vistas[$obj->donde_idu])) {
				$vistas[$obj->donde_idu] = [];
			}
			$vistas[$obj->donde_idu][] = $obj;
		}

		return $vistas;
	}

	#
	public function quitarImagen($obj)
	{
		$sql = "UPDATE vistas_previas SET image='' WHERE idu=? LIMIT 1";
		self::query($sql, [$obj->idu]);
		_mail::send(
			'ia@roleplus.app',
			'Imagen previa quitada',
			"Imagen de idu {$obj->idu} borrada ({$obj->image})."
		);
	}
}
