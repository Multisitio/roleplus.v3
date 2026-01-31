<?php
/**
 */
class Archivos
{
	#
	public function foto2avatar($name)
	{
		$dir = '/img/usuarios/' . Session::get('idu');
		$name = preg_replace('/[^a-z0-9\.]/i', '_', $name);

		#$name = _str::uid();

		Thumbnail::make("$dir/$name", 'xxs');
		return $name;
	}

	#
	static private function obtenerCarpetaBase($file_type, $to='')
	{
		$dir = false;
		if ($file_type == 'img' || $file_type == 'imagenes') {
			$dir = ( ! $to) ? 'img/usuarios/' . Session::get('idu') : 'img';
			#_var::die([$file_type, $to, $dir]);
		}
		if ($file_type == 'mp3') {
			$dir = 'mp3';
		}
		if ($file_type == 'mp4') {
			$dir = 'mp4';
		}
		return $dir;
	}

	#
	static private function crearRuta($dir_base, $to='')
	{
		$dir = empty($to) ? $dir_base : "$dir_base/$to";
		$dir = str_replace(['..', '//'], ['', '/'], $dir);
		if ( ! realpath($dir)) {
			mkdir($dir, 0755, 1);
		}
		return $dir;
	}

	#
	static private function establecerNombre($name, $new_name='')
	{
		$name = empty($new_name) ? basename($name) : $new_name;
		$name = preg_replace('/[^a-z0-9\.]/i', '_', $name);

		if ( ! preg_match('/(gif|jpeg|jpg|png|mp3|mp4|svg|svgz|webp)$/i', $name) ) {
			return false;
		}
		return _str::uid() . "_$name";
		#return _str::uid();
	}

	#
	static private function crearMiniaturas($dir, $name='')
	{
		if (strstr($dir, 'img') && ! preg_match('/\.(gif|svg)$/i', $name) ) {
			Thumbnail::make("$dir/$name", 'm');
			Thumbnail::make("$dir/$name", 'l');
		}
	}

	#
	public function incluir($files_group, $to='', $new_name='')
	{
		#_var::echo([$files_group, $to, $new_name]);
		/*if ($_SERVER['REMOTE_ADDR'] == '137.101.253.61') {
			_mail::send('ia@roleplus.app', 'Archivos->incluir 1', print_r([$files_group, $to, $new_name], 1));
		}*/
		#unset($_FILES); // Vacío la variable para que no pase de nuevo por aquí

		$names = [];

		foreach ($files_group as $file_type=>$files) {

			foreach ($files['name'] as $key=>$name) {
                if ($files['error'][$key] > 0) {
					continue;
				}

				$dir = self::obtenerCarpetaBase($file_type, $to);
				if ( ! $dir) {
					continue;
				}

				$dir = self::crearRuta($dir, $to);
				#_var::echo([$dir, $to]);

				$name = self::establecerNombre($name, $new_name);
				if ( ! $name) {
					continue;
				}

				$destino = "$dir/$name";

				# Solo mover si realmente es un upload válido (seguridad)
				if (is_uploaded_file($files['tmp_name'][$key])) {
					$r = move_uploaded_file($files['tmp_name'][$key], $destino);
				} else {
					$r = false;
				}

				# Registrar nombre SOLO si el movimiento fue OK
				if ($r) {
					$names[$destino] = $name;
					self::crearMiniaturas($dir, $name);
				}

				/*if ($_SERVER['REMOTE_ADDR'] == '137.101.253.61') {
					_mail::send('ia@roleplus.app', 'Archivos->incluir 2', print_r([$files_group, $to, $new_name, $destino, $names, $r], 1));
				}*/
				#_var::echo(['Hola', $name]);
			}
		}

		#_var::echo([$names, $name]);
		if (empty($names)) {
			return [];
		}
		$dirname = array_key_last($names);
		$r = (count($names) > 1) ? $names : $names[$dirname];
		#_var::die(['Adios', $names[$dirname]]);
		return $r;
	}
}
