<?php
/**
 */
class Usuario
{
    #
    public static function apodo($usu)
    {
        ob_start();
        ?><a href="/usuarios/perfil/<?=$usu->hashtag?>"><b><?=$usu->apodo?></b></a><?php
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }

    #
    public static function avatar($usu, $miniatura='xs', $test=0)
    {
        $title = t('Avatar y perfil de ') . $usu->apodo;
        $usuarios_idu = empty($usu->usuarios_idu) ? $usu->idu : $usu->usuarios_idu;
        $avatar_img = "img/usuarios/$usuarios_idu/$miniatura.$usu->avatar";
        $img = "img/usuarios/$usuarios_idu/$usu->avatar";

        if ($test) {
            $r[] = file_exists($img);
            $r[] = file_exists("/$img");
            _var::die($r);
        }

        if ( ! $usu->avatar || ! _file::exists($img)) {
            $rol = empty($usu->rol) ? 0 : $usu->rol;
            $rol_name = Config::get('roles.singular_simple')[$rol]; 
            $rol_name = _str::normalize($rol_name);
            $avatar_img = "img/usuarios/$miniatura.$rol_name.png";
        }
        $tamanyo = Config::get('vistas.miniaturas')[$miniatura] . 'px';
        ob_start();
        ?><a class="avatar" href="/usuarios/perfil/<?=$usu->hashtag?>" title="<?=$title?>"><img alt="<?=$title?>" height="<?=$tamanyo?>" loading="lazy" src="<?="/$avatar_img"?>" width="<?=$tamanyo?>"></a><?php
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }

    #
    public static function token($usu, $miniatura='xxs')
    {
        return str_replace('<img alt', '<img class="token" alt', self::avatar($usu, $miniatura));
    }
}
