<?php
/**
 */
class ImgController extends AppController
{
    protected function before_filter()
    {
        View::select('', null);
    }

    # $action_name = usuarios; $params[0] = $usuarios_idu; $params[1] = m.$img;
    public function __call($action_name, $params)
    {      
        if (empty($params[0])) {
            throw new KumbiaException('No es una imagen');
        }

        # id de usuario
        $usuarios_idu = empty($params[1]) ? '' : '/' . $params[0];

        # nombre de la imagen
        $nombre = empty($params[1]) ? $params[0] : $params[1];

        # separamos el tamaño de la miniatura del nombre dado
        preg_match('/(xxs\.|xs\.|s\.|m\.|l\.|xl\.|xxl\.)?(.+)/i', $nombre, $partes);
        if (empty($partes[1])) {
            throw new KumbiaException('Miniatura no aceptada');
        }

        # tamaño de la miniatura
        $miniatura = empty($partes[2]) ? '' : str_replace('.', '', $partes[1]);

        # nombre de la no-miniatura
        $nombre_original = trim($partes[2]);

        # Imagen original
        $imagen_original = "img/$action_name$usuarios_idu/$nombre_original";   

        # Si se solicitó una miniatura...
        if ($imagen_original and $miniatura) {
            # Se crea una miniatura
            Thumbnail::make($imagen_original, $miniatura);
        }
    }
}
