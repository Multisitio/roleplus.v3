<?php
/**
 */
class ImgController extends AppController
{
    protected function before_filter()
    {
        View::select('', null);
    }

    # $action_name es igual a usuarios, $params[0] a $usuarios_idu y $params[1] a m.$img;
    public function __call($action_name, $params)
    {     
        /*if ($_SERVER['REMOTE_ADDR'] == '137.101.253.61') {
            mail('ia@roleplus.app', 'SUBIDA IMAGEN 1', print_r([$action_name, $params], 1));
        }*/

        if (empty($params[0])) {
            throw new KumbiaException('No es una imagen');
        }

        # id de usuario
        $usuarios_idu = empty($params[1]) ? '' : '/' . $params[0];

        # nombre de la imagen
        $nombre = empty($params[1]) ? $params[0] : $params[1]; 

        /*if ($_SERVER['REMOTE_ADDR'] == '137.101.253.61') {
            mail('ia@roleplus.app', 'SUBIDA IMAGEN 2', print_r([$nombre], 1));
        }*/

        # http://localhost/img/consejos/cb5aecaa32d_regreso_e1639877499795.jpg
        # separamos el tamaño de la miniatura del nombre dado
        preg_match('/(xxs\.|xs\.|s\.|m\.|l\.|xl\.|xxl\.)?(.+)/i', $nombre, $partes);
        if (empty($partes[1])) {
            throw new KumbiaException('Miniatura no aceptada');
        }

        /*if ($_SERVER['REMOTE_ADDR'] == '137.101.253.61') {
            mail('ia@roleplus.app', 'SUBIDA IMAGEN 3', print_r([$partes], 1));
        }*/

        # tamaño de la miniatura
        $miniatura = empty($partes[2]) ? '' : str_replace('.', '', $partes[1]);

        # nombre de la no-miniatura
        $nombre_original = trim($partes[2]);

        # Imagen original
        $imagen_original = "img/$action_name$usuarios_idu/$nombre_original";

        /*if ($_SERVER['REMOTE_ADDR'] == '137.101.253.61') {
            mail('ia@roleplus.app', 'SUBIDA IMAGEN 4', print_r([$imagen_original, $miniatura], 1));
        }*/

        # Si se solicitó una miniatura...
        if ($imagen_original && $miniatura) {
            # Se crea una miniatura
            $imagen_nueva = Thumbnail::make($imagen_original, $miniatura);
            Redirect::to($imagen_nueva);
        }
    }
}
