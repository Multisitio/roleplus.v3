<?php
/**
 */
class Elementos extends LiteRecord
{
	#
	public function salvar($cat)
	{
		/* ⮞ Defaults y uid cuando no hay idu (INSERT) */
		$vals[] = Session::get('idu');
		$vals[] = (string)$cat['aventuras_idu'];
		$vals[] = empty($cat['peso']) ? 0 : (string)$cat['peso'];
		$vals[] = empty($cat['nombre']) ? 'Sin nombre' : (string)$cat['nombre'];
		$vals[] = empty($cat['tipo']) ? 'texto' : (string)$cat['tipo'];
		if (empty($cat['idu'])) {
			$vals[] = _str::uid();
		}
		$vals[] = empty($cat['texto']) ? '' : $cat['texto'];

		/* ⮞ Mantener valores existentes; solo procesar $_FILES si NO vienen ya nombres */
		$cat['fotos'] = empty($cat['fotos']) ? '' : $cat['fotos'];
		$cat['mp3'] = empty($cat['mp3']) ? '' : $cat['mp3'];
		$cat['mp4'] = empty($cat['mp4']) ? '' : $cat['mp4'];

		if (empty($cat['fotos']) && empty($cat['mp3']) && empty($cat['mp4']) && !empty($_FILES)) {
			$files = (new Archivos)->incluir($_FILES);

			if (is_array($files)) {
				foreach ($files as $name) {
					if (preg_match('/\.(gif|jpeg|jpg|png|svg|svgz|webp)$/i', $name)) {
						$cat['fotos'] = $name;
					}
					elseif (preg_match('/\.(mp3)$/i', $name)) {
						$cat['mp3'] = $name;
					}
					elseif (preg_match('/\.(mp4)$/i', $name)) {
						$cat['mp4'] = $name;
					}
				}
			}
			elseif ($files) {
				$name = $files;
				if (preg_match('/\.(gif|jpeg|jpg|png|svg|svgz|webp)$/i', $name)) {
					$cat['fotos'] = $name;
				}
				elseif (preg_match('/\.(mp3)$/i', $name)) {
					$cat['mp3'] = $name;
				}
				elseif (preg_match('/\.(mp4)$/i', $name)) {
					$cat['mp4'] = $name;
				}
			}
		}

		/* ⮞ Media + resto de campos comunes */
		$vals[] = $cat['fotos'];
		$vals[] = $cat['mp3'];
		$vals[] = $cat['mp4'];
		$vals[] = empty($cat['youtube']) ? '' : _var::getUrlVar($cat['youtube']);
		$vals[] = $cat['enviado'];

		/* ⮞ Inserción vs actualización (posiciones saneadas) */
		if (empty($cat['idu'])) {
            $vals[] = rand(100, 500);
            $vals[] = rand(100, 500);
            $vals[] = date('Y-m-d H:i:s');
		}
		else {
			$posx = (int)preg_replace('/[^\d\.]/', '', (string)$cat['posicion_x']);
			$posy = (int)preg_replace('/[^\d\.]/', '', (string)$cat['posicion_y']);
			$vals[] = $posx;
			$vals[] = $posy;
			$vals[] = Session::get('idu');
			$vals[] = (string)$cat['idu'];
		}

		/* ⮞ SQL */
		if (empty($cat['idu'])) {
			$sql = 'INSERT INTO elementos SET usuarios_idu=?, aventuras_idu=?, peso=?, nombre=?, tipo=?, idu=?, texto=?, fotos=?, mp3=?, mp4=?, youtube=?, enviado=?, posicion_x=?, posicion_y=?, creado=?';
		}
		else {
			$sql = 'UPDATE elementos SET usuarios_idu=?, aventuras_idu=?, peso=?, nombre=?, tipo=?, texto=?, fotos=?, mp3=?, mp4=?, youtube=?, enviado=?, posicion_x=?, posicion_y=? WHERE (usuarios_idu=? OR usuarios_idu IS NULL) AND idu=?';
		}

		$r = self::query($sql, $vals);
		_var::die([$sql, $vals, $r]);
	}

	# 3.0
	public function eliminar($post_or_idu)
	{
		$aplicar_en = empty($post_or_idu['aplicar_en']) ? 'aventura' : $post_or_idu['aplicar_en'];
		$idu = empty($post_or_idu['idu']) ? $post_or_idu : $post_or_idu['idu'];

		if ($aplicar_en == 'partida' || $aplicar_en == 'ambas') {
			(new Partidas_elementos)->eliminar($idu);
		}

		if ($aplicar_en == 'aventura' || $aplicar_en == 'ambas') {
			$sql = 'DELETE FROM elementos WHERE (usuarios_idu=? OR usuarios_idu IS NULL) AND idu=?';
			self::query($sql, [Session::get('idu'), $idu]);
			Session::delete('elementos_idu');
		}
	}

	#
	public function guardarPosicion($idu, $x, $y)
	{
		$x = (int)preg_replace('/[^\d+]/', '', $x);
		$y = (int)preg_replace('/[^\d+]/', '', $y);
		$sql = "UPDATE elementos SET posicion_x=?, posicion_y=? WHERE idu=?";
		self::query($sql, [$x, $y, $idu]);
	}

	# Revisar para eliminar
	public function tipos()
	{
		return [
			'mp3'=>t('MP3'),
			'mp4'=>t('MP4'),
			'texto'=>t('Texto'),
			'fotos'=>t('JPEG/PNG/SVG'),
			'youtube'=>t('YouTube')
		];
	}

	# 3.0
	public function todos($aventuras_idu)
	{
		$sql = 'SELECT * FROM elementos WHERE aventuras_idu=? ORDER BY peso';
		return self::all($sql, [$aventuras_idu]);
	}

	# 3.0
	public function uno($idu='')
	{
		$sql = 'SELECT * FROM elementos WHERE idu=?';
		$uno = self::first($sql, [$idu]);
		return $uno ? $uno : parent::cols();
	}
}
