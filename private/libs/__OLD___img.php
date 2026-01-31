<?php
/**
 */
class _img
{
    /**
    * Abre la imagen con el nombre pasado como parametro y devuelve un array con la imagen y el tipo de imagen.
    *
    * @param type $nombre Nombre completo de la imagen incluida la ruta y la extension.
    * @return Devuelve la imagen abierta.
    */
    static function abrirImagen($nombre)
    {
        $info = getimagesize($nombre);

        if ($info["mime"] == "image/jpeg")
        {
            $imagen = imagecreatefromjpeg($nombre);
        }
        elseif ($info["mime"] == "image/gif")
        {
            $imagen = imagecreatefromgif($nombre);
        }
        elseif ($info["mime"] == "image/png")
        {
            $imagen = imagecreatefrompng($nombre);
        }

        $resultado[0]= $imagen;
        $resultado[1]= $info["mime"];
        return $resultado;
    }

    /**
     * Guarda la imagen con el nombre pasado como parametro.
     *
     * @param type $imagen La imagen que se quiere guardar.
     * @param type $nombre Nombre completo de la imagen incluida la ruta y la extension.
     * @param type $tipo Formato en el que se guardara la imagen.
     */
    static function guardarImagen($imagen, $nombre, $tipo)
    {
        #_var::die([$imagen, $nombre, $tipo]);
        # El 100 es la calidad de la imagen (entre 1 y 100. Con 100 sin compresion ni perdida de calidad.).
        if ($tipo == 'image/jpeg')
        {
            imagejpeg($imagen, $nombre, 100);
        }
        elseif ($tipo == 'image/gif')
        {
            imagegif($imagen, $nombre);
        }
        # El 9 es grado de compresion de la imagen (entre 0 y 9. Con 9 maxima compresion pero igual calidad.).
        elseif ($tipo == 'image/png')
        {
            imagepng($imagen, $nombre, 9);
        }
    }

    /**
     * Crea un thumbail de un imagen con el ancho y el alto pasados como parametros,
     * recortando en caso de ser necesario la dimension mas grande por ambos lados.
     *
     * @param type $nombreImagen Nombre completo de la imagen incluida la ruta y la extension.
     * @param type $nombreThumbnail Nombre completo para el thumbnail incluida la ruta y la extension.
     * @param type $nuevoAncho Ancho para el thumbnail.
     * @param type $nuevoAlto Alto para el thumbnail.
     */
    static function crearThumbnailRecortado($nombreImagen, $nombreThumbnail, $nuevoAncho, $nuevoAlto='')
    {
        // Obtiene las dimensiones de la imagen.
        list($ancho, $alto) = getimagesize($nombreImagen);
        if ( ! $alto) {
            return;
        }
        #_var::die([2, $nombreImagen, $nombreThumbnail, $nuevoAncho, $nuevoAlto]);

        // Establece el alto para el thumbnail si solo se paso el ancho.
        if ( ! $nuevoAlto)
        {
            $factorReduccion = $ancho / $nuevoAncho;
            #die('<pre>'.print_r([$nuevoAlto, $alto, $factorReduccion],1));
            $nuevoAlto = $alto / $factorReduccion;
        }

        // Si la division del ancho de la imagen entre el ancho del thumbnail es mayor
        // que el alto de la imagen entre el alto del thumbnail entoces igulamos el
        // alto de la imagen  con el alto del thumbnail y calculamos cual deberia ser
        // el ancho para la imagen (Seria mayor que el ancho del thumbnail).
        // Si la relacion entre los altos fuese mayor entonces el altoImagen seria
        // mayor que el alto del thumbnail.
        if ($ancho/$nuevoAncho > $alto/$nuevoAlto)
        {
            $altoImagen = $nuevoAlto;
            $factorReduccion = $alto / $nuevoAlto;
            $anchoImagen = $ancho / $factorReduccion;
        }
        else
        {
            $anchoImagen = $nuevoAncho;
            $factorReduccion = $ancho / $nuevoAncho;
            $altoImagen = $alto / $factorReduccion;
        }

        // Abre la imagen original.
        list($imagen, $tipo) = self::abrirImagen($nombreImagen);

        if ($tipo == 'image/gif')
        {
            #_var::die([$nombreImagen, $nombreThumbnail, $nuevoAncho, $nuevoAlto]);
            $nombreWebm = str_replace('.gif', '.webm', $nombreImagen);
            $nombreMp4 = str_replace('.gif', '.mp4', $nombreImagen);
            if ( ! realpath($nombreWebm))
            {
                system(CMD_PATH . "ffmpeg -i $nombreImagen -c vp9 -b:v 0 -crf 41 $nombreWebm");
                system(CMD_PATH . "ffmpeg -i $nombreImagen -b:v 0 -crf 25 $nombreMp4");
                #_var::die("ffmpeg -i $nombreImagen $nombreMp4");
            }
            return;
        }

        // Crea la nueva imagen (el thumbnail).
        $thumbnail = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);

        // Si la relacion entre los anchos es mayor que la relacion entre los altos
        // entonces el ancho de la imagen que se esta creando sera mayor que el del
        // thumbnail porlo que se centrara para que se corte por la derecha y por la
        // izquierda. Si el alto fuese mayor lo mismo se cortaria la imagen por arriba
        // y por abajo.
        if ($ancho/$nuevoAncho > $alto/$nuevoAlto)
        {
            imagecopyresampled($thumbnail , $imagen, ($nuevoAncho-$anchoImagen)/2, 0, 0, 0, $anchoImagen, $altoImagen, $ancho, $alto);
        }
        else
        {
            imagecopyresampled($thumbnail , $imagen, 0, ($nuevoAlto-$altoImagen)/2, 0, 0, $anchoImagen, $altoImagen, $ancho, $alto);
        }

        // Guarda la imagen.
        self::guardarImagen($thumbnail, $nombreThumbnail, $tipo);
    }

    static function make($usuarios_idu, $name, $ancho='')
    {
        if ( ! $name) {
            return;
        }
        $name = preg_replace('/[^a-z0-9\.]/i', '_', $name);
        $anchos = [
            'xxs' => 48,
            'xs' => 150,
            's' => 250,
            'm' => 530,
            'l' => 692,
            'xl' => 1200,
            'xxl' => 1920,
        ];
        if ( ! $ancho) {
            $ancho = explode('.', $name, 2)[0];
            $name = explode('.', $name, 2)[1];
        }
        $usuarios_idu = h($usuarios_idu);
        $dir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . "/img/usuarios/$usuarios_idu";

        _img::crearThumbnailRecortado("$dir/$name", "$dir/$ancho.$name", $anchos[$ancho]);
        /*$info = getimagesize("$dir/$name");
        header('Content-Type: ' . $info['mime']);
        return "$dir/$ancho.$name";*/
    }
}
