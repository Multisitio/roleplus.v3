<?php
/**
 */
class Translator extends LiteRecord
{
	#
	public function getEntriesInFiles($dir='', $idioma='EN', $from=0, $to=0)
	{	
        $files = scandir(APP_PATH . $dir);

        foreach ($files as $file) {
			if ($to && $from == $to) {
				#return $entries;
			}
			++$from;

            if ($file == '.' || $file == '..') {
                continue;
            }

			$path = empty($dir) ? $file : "$dir/$file";
			
			if (empty($entries[$path])){
				$entries[$path] = [];
			}

            if (is_dir(APP_PATH . $path)) {
                $entries[$path] = self::getEntriesInFiles($path, $idioma, $from, $to);
			}
			else {
                $entries[$path] = self::getEntriesInFile($path, $idioma);
			}
        }

		if (empty($entries)){
			$entries = [];
		}
		return $entries;
	}

	#
	public function getEntriesInFile($path, $idioma='en')
	{	
        $code = file_get_contents(APP_PATH . $path);
				
		preg_match_all("/[\s\(\[=]{1}t\('([^']+)'\)/u", $code, $matches);
			
		if ( ! empty($matches[1])) {

			foreach ($matches[1] as $i=>$traducir) {
				self::marcarTraduccionActiva($idioma, $traducir);
				$traducido = self::comprobarSiEstaTraducido($idioma, $traducir);
				if ( ! empty($traducido->traduccion)) {
					continue;
				}

				/*$json = _link::curl_get_file_contents('https://api.mymemory.translated.net/get?q='.urlencode($traducir)."&langpair=es|$idioma");
				
				$traduccion = empty($json)
					? '' 
					: json_decode($json)->responseData->translatedText;*/

				$pregunta = "Traduce con solo una respuesta, la más frecuente, con texto plano, sin contexto ni explicaciones, no añadas más si es una palabra lo que hay que traducir, al idioma iso($idioma) lo siguiente: " . trim($traducir);
				$traduccion = (new Respuestas)->preguntarAOpenAi($pregunta);
				$traduccion = trim($traduccion);

				$matches[1][$i] = "$traducir => $traduccion";

				empty($traducido->traducir)
					? self::crearTraduccion($idioma, $traducir, $traduccion)
					: self::actualizarTraduccion($idioma, $traducir, $traduccion);

				_var::flush($matches[1][$i]);
			}

			return $matches[1];
		}
	}

	#
	public function marcarTraduccionActiva($idioma, $traducir)
	{
		$sql = 'UPDATE traducciones SET activa=1 WHERE idioma=? AND BINARY traducir=?';
		self::query($sql, [$idioma, $traducir]);
	}

	#
	public function comprobarSiEstaTraducido($idioma, $traducir)
	{
		$sql = 'SELECT * FROM traducciones WHERE idioma=? AND BINARY traducir=?';
		return self::first($sql, [$idioma, $traducir]);
	}

	#
	public function crearTraduccion($idioma, $traducir, $traduccion)
	{
		$sql = 'INSERT INTO traducciones SET idioma=?, traducir=?, traduccion=?, activa=1';
		self::query($sql, [$idioma, $traducir, _html::hd($traduccion)]);
	}

	#
	public function actualizarTraduccion($idioma, $traducir, $traduccion)
	{
		$sql = 'UPDATE traducciones SET traduccion=?, activa=1 WHERE idioma=? AND BINARY traducir=?';
		self::query($sql, [_html::hd($traduccion), $idioma, $traducir]);
	}

	#
	public function desactivartodas()
	{
		$sql = 'UPDATE traducciones SET activa=0';
		self::query($sql);
	}

	#
	public function borrarDesactivadas()
	{
		$sql = 'DELETE FROM traducciones WHERE activa=0';
		self::query($sql);
	}
}
