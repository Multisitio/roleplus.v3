<?php
/**
 */
trait UsuariosPerfil
{
    # Perfil
    public function establecerIdioma($iso='', $debug=0)
    {
        if (empty($iso)) {
            if (empty($_COOKIE['usuario']['idioma'])) {
                if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                    $iso = 'ES';
                }
                else {
                    $iso = mb_strtoupper(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2));
                }
            }
            else {
                $iso = $_COOKIE['usuario']['idioma'];
            }
        }
        $idiomas = Config::get('combos.idiomas');
        $iso = empty($idiomas[$iso]) ? 'ES' : $iso;
                
        _cookie::add('usuario[idioma]', $iso);

        if (Session::get('idu')) {
            $sql = 'UPDATE usuarios SET idioma=? WHERE idu=?';
            parent::query($sql, [$iso, Session::get('idu')]);
        }
        return $iso;
    }

    # Perfil
    public function guardarPerfil($post)
    {
        if ( ! self::validar($post)) {
            return false;
        }

        $perfil_anterior = $usuario = self::uno();

        if ($post['apodo'] <> $perfil_anterior->apodo) {
            (new Historico)->add('apodos', $perfil_anterior->apodo, $post['apodo']);
        }

        $avatar = empty($_FILES['avatar']['name']) 
            ? $post['avatar_anterior']
            : _file::save($_FILES['avatar'], "img/usuarios/$usuario->idu", 's');

        if ($post['avatar_anterior'] <> $perfil_anterior->avatar) {
            unlink("img/usuarios/$usuario->idu/s.$perfil_anterior->avatar");
            unlink("img/usuarios/$usuario->idu/xs.$perfil_anterior->avatar");
            unlink("img/usuarios/$usuario->idu/xxs.$perfil_anterior->avatar");
        }

        $fondo_cabecera = empty($_FILES['fondo_cabecera']['name'])
            ? $post['cabecera_anterior']
            : _file::save($_FILES['fondo_cabecera'], "img/usuarios/$usuario->idu", 'l', true);

        if ($post['cabecera_anterior'] <> $perfil_anterior->fondo_cabecera) {
            unlink("img/usuarios/$usuario->idu/l.$perfil_anterior->fondo_cabecera");
            unlink("img/usuarios/$usuario->idu/l.$perfil_anterior->fondo_cabecera");
        }

        $fondo_general = empty($_FILES['fondo_general']['name'])
            ? $post['fondo_anterior']
            : _file::save($_FILES['fondo_general'], "img/usuarios/$usuario->idu", 'xxl', true);

        if ($post['fondo_anterior'] <> $perfil_anterior->fondo_general) {
            unlink("img/usuarios/$usuario->idu/xxl.$perfil_anterior->fondo_general");
        }

        $vals[] = self::establecerIdioma($post['idioma']);
        $vals[] = $post['apodo'];
        $vals[] = parent::getSlug('usuarios', _url::slug($post['apodo']));
        $vals[] = $post['eslogan'];
        $vals[] = $post['sobre_mi'];
        $vals[] = $avatar;
        $vals[] = $fondo_cabecera;
        $vals[] = $fondo_general;
        $vals[] = Session::get('idu');
        #_var::die($vals);
        
        $sql = 'UPDATE usuarios SET idioma=?, apodo=?, slug=?, eslogan=?, sobre_mi=?, avatar=?, fondo_cabecera=?, fondo_general=? WHERE idu=?';
        parent::query($sql, $vals);

        Session::setArray('toast', t('Perfil salvado.'));
        
        return true;
    }
}
