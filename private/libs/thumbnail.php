<?php
/**
 */
class Thumbnail
{
    private $img;
    private $imgDest;
    private $metadata;
    private $quality = 75;

    public function __construct($imgfile)
    {
        $info = getimagesize($imgfile);

        switch ($info[2]) {
            case IMAGETYPE_GIF:
                $this->metadata['format'] = 'GIF';
                $this->img                = imagecreatefromgif($imgfile);
                break;
            case IMAGETYPE_JPEG:
                $this->metadata['format'] = 'JPEG';
                $this->img                = imagecreatefromjpeg($imgfile);
                break;
            case IMAGETYPE_PNG:
                $this->metadata['format'] = 'PNG';
                $this->img                = imagecreatefrompng($imgfile);
                break;
            case IMAGETYPE_WEBP:
                $this->metadata['format'] = 'WEBP';
                $this->img                = imagecreatefromwebp($imgfile);
                break;
            default:
                throw new KumbiaException('Formato no soportado');
        }

        $this->metadata['width']  = imagesx($this->img);
        $this->metadata['height'] = imagesy($this->img);
    }

    public function size_height($size)
    {
        $this->metadata['tinggi_thumb'] = $size;
        $this->metadata['lebar_thumb']  = round(($this->metadata['tinggi_thumb'] / $this->metadata['height']) * $this->metadata['width']);
    }

    public function size_width($size)
    {
        $this->metadata['lebar_thumb']  = $size;
        $this->metadata['tinggi_thumb'] = round(($this->metadata['lebar_thumb'] / $this->metadata['width']) * $this->metadata['height']);
    }

    public function size_auto($size)
    {
        if ($this->metadata['width'] > $this->metadata['height']) {
            $this->size_width($size);
            return;
        }
        $this->size_height($size);
    }

    public function jpeg_quality($quality)
    {
        $this->quality = $quality;
    }

    public function show()
    {
        header('Content-Type: image/'.$this->metadata['format']);
        $this->createImg();
    }

    public function save($save = '')
    {
        if (empty($save)) {
            $save = strtolower('img/l.'.$this->metadata['format']);
        }
        return $this->createImg($save);
    }

    protected function createImg($file = null)
    {
        $this->imgDest = imagecreatetruecolor($this->metadata['lebar_thumb'], $this->metadata['tinggi_thumb']);

        if ($this->imgDest) {
            # Estas lineas son para conservar la transparencia de los PNG
            imagesavealpha($this->imgDest, true);
            $color = imagecolorallocatealpha($this->imgDest, 225, 225, 225, 127);
            imagefill($this->imgDest, 0, 0, $color);

            imagecopyresampled($this->imgDest, $this->img, 0, 0, 0, 0, $this->metadata['lebar_thumb'], $this->metadata['tinggi_thumb'], $this->metadata['width'], $this->metadata['height']);
        }

        switch($this->metadata['format']) {
            case 'JPEG':
                return imagejpeg($this->imgDest, $file, $this->quality);
            case 'PNG':
                return imagepng($this->imgDest, $file);
            case 'GIF':
                return imagegif($this->imgDest, $file);
            case 'WEBP':
                return imagewebp($this->imgDest, $file);
            default:
                throw new KumbiaException('No es una imagen aceptada');
        }
    }

    # $imagen_original = "/img/usuarios/f7a668c1387/l.nombre_imagen.ext";
    # 280, 320, 360, 375, 411, 414, 540, 768, 1024
    static function make($imagen_original, $miniatura='l')
    {   
        #_var::die($imagen_original);
        if ( ! file_exists($imagen_original)) {
            throw new KumbiaException('La imagen original no existe');
        }

        $anchos = Config::get('vistas.miniaturas'); 
        $thu = new Thumbnail($imagen_original);
        if ( ! $thu) {
            throw new KumbiaException('No es una imagen');
        }

        $thu->size_width($anchos[$miniatura]);
        $dir = dirname($imagen_original);
        $name = basename($imagen_original);
        $imagen_nueva = "$dir/$miniatura.$name";

        $r = $thu->save($imagen_nueva);
#_var::die([$imagen_nueva, $r]);
        return $imagen_nueva;
    }
}
