<?php
/**
 */
class _file
{
	# 1
	public static function saveFiles($files, $save_to, $size='', $delete_original=false)
	{
		$names = [];
		foreach ($files['name'] as $key=>$val) {

			$file['name'] = $files['name'][$key];

			if ( ! $file['name']) {
				continue;
			}

			$file['full_path'] = $files['full_path'][$key];
			$file['type']      = $files['type'][$key];
			$file['tmp_name']  = $files['tmp_name'][$key];
			$file['error']     = $files['error'][$key];
			$file['size']      = $files['size'][$key];

			$names[] = self::save($file, $save_to, $size, $delete_original);
		}
		return implode(', ', $names);
	}

	# 1.1
	public static function save($file, $save_to, $size='', $delete_original=false)
	{
		
		#_var::die([$file['tmp_name'], "$save_to/$filename", $size, $delete_original]);

		if ($file['error']>0) {
			return;
		}

		$ext = explode('/', $file['type'])[1];

		$filename = _str::uid() . ".$ext";

		self::doPath("$save_to/$filename");

		move_uploaded_file($file['tmp_name'], "$save_to/$filename");

		if ($size) {
			#$start_time = microtime(1);
			self::doMinis($save_to, $filename, $ext, $size, $delete_original);
			#die(microtime(1)-$start_time);
			#_mail::send('admin@mtgsearch.it', 'Tiempo en hace la mini', microtime(1)-$start_time);
		}

		return $filename;
	}

	# 1.1.1
	public static function doPath($filepath)
	{
		$dirname = dirname($filepath);
		$dirname = str_replace(
			['..', '//', '\\\\', '\\'],
			[  '',  '/',    '/',  '/'],
			$dirname);
			
		if ( ! realpath($dirname)) {
			mkdir($dirname, 0755, 1);
		}
	}

	# 1.1.2
	public static function doMinis($save_to, $filename, $ext, $size, $delete_original)
	{
		if ( ! is_array($size)) {
			$size = [$size];
		}

		foreach ($size as $siz) {
			if ($ext == 'gif') {
				$filename = self::gifToMp4(PUB_PATH . "$save_to/$filename", $siz);
			}
			elseif ($ext == 'webm') {
				$filename = self::gifToWebm(PUB_PATH . "$save_to/$filename", $siz);
			}
			else {
				Thumbnail::make("$save_to/$filename", $siz, true);
			}
		}

		if ($delete_original) {
			unlink("$save_to/$filename");
		}
	}

	# 1.1.2.1
	public static function gifToMp4($from_filename, $size)
	{
		$to_filename = str_replace('.gif', '.mp4', $from_filename);
		$dirname = dirname($to_filename);
		$basename = basename($to_filename);

		if ( ! realpath($to_filename)) {
			system(CMD_PATH . "ffmpeg -i $from_filename -b:v 0 -crf 25 $dirname/$size.$basename");
		}
		return $basename;
	}

	# 1.1.2.2
	public static function gifToWebm($from_filename, $size)
	{
		$to_filename = str_replace('.gif', '.webm', $from_filename);
		$dirname = dirname($to_filename);
		$basename = basename($to_filename);

		if ( ! realpath($to_filename)) {
			system(CMD_PATH . "ffmpeg -i $from_filename -c vp9 -b:v 0 -crf 41 $dirname/$size.$basename");
		}
		return $basename;
	}

	# 2
	public static function quit($file)
	{
        if (file_exists($file)) {
            return unlink($file);
        }
		return false;
	}

    # 3
	public static function exists($file='')
	{
		return file_exists(ltrim($file, '/'));

		if ( ! $file) {
			return false;
		}
		$handle = @fopen($file, "r");
		if ($handle === false) {
			return false;
		}
		fclose($handle);
		return true;
	}

	# 4 -> pte de implementar
	public static function heicToJpg($from_filename='', $size='')
	{
		$inputFilePath = 'ruta/imagen.heic';
		$outputFilePath = 'ruta/imagen.jpg';

		try {
			// Cargar la imagen HEIC usando Imagick
			$imagick = new Imagick();
			$imagick->readImage($inputFilePath);
			
			// Convertir la imagen a formato JPG
			$imagick->setImageFormat('jpg');
			
			// Establecer la calidad del JPG (opcional)
			$imagick->setImageCompressionQuality(90); // Cambia el valor según tus necesidades
			
			// Guardar la imagen convertida en formato JPG
			$imagick->writeImage($outputFilePath);
			
			// Liberar memoria
			$imagick->clear();
			$imagick->destroy();
			
			echo 'Imagen convertida de HEIC a JPG exitosamente.';
		} catch (ImagickException $e) {
			echo 'Error al convertir la imagen: ' . $e->getMessage();
		}
	}
}
