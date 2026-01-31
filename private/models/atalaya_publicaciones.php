<?php
/**
 */
class Atalaya_publicaciones extends LiteRecord
{
	#
	public function copiarTipoYSubtipoAEtiquetas()
	{
		$sql = 'SELECT id, tipo, subtipo, etiquetas, etiquetas_formateadas, titulo, publicado FROM publicaciones ORDER BY publicado DESC';
		$publicaciones = parent::all($sql);

		foreach ($publicaciones as $pub) {

			/*$etiquetas = [];
			$etiquetas[] = $pub->tipo;
			$etiquetas[] = $pub->subtipo;

			$etiquetas_formateadas = self::formatearEtiquetas($etiquetas);

			$etiquetas = implode(' ', $etiquetas);

			$sql = 'UPDATE publicaciones SET etiquetas=?, etiquetas_formateadas=? WHERE id=?';
			parent::query($sql, [$etiquetas, $etiquetas_formateadas, $pub->id]);*/

			echo "<hr><h2>$pub->titulo</h2>";
			echo "<small>$pub->publicado</small>";
			echo "<p><b>Tipo:</b> $pub->tipo</p>";
			echo "<p><b>Subtipo:</b> $pub->subtipo</p>";
			echo "<p><b>Etiquetas:</b> $pub->etiquetas</p>";
			echo "<p><b>Etiquetas formateadas:</b> $pub->etiquetas_formateadas</p>";
			_var::flush();
		}
	}

	#
	public function formatearEtiquetas($etiquetas_seperadas)
	{
		$etiquetas_formateadas = [];
		foreach ($etiquetas_seperadas as $eti) {
			if ( ! $eti) {
				continue;
			}
			$hashtag = _str::hashtag($eti);
			$etiquetas_formateadas[] = 
			"<a class=\"tag\" href=\"/publicaciones/buscar/$hashtag\">$hashtag</a>";
		}
		return implode(' ', $etiquetas_formateadas);
	}
}
