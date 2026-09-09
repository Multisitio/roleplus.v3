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

        // Complete all uploads before changing references. Keep historical files
        // available to existing URLs, including when one of the uploads fails.
        try {
            $images = [];
            foreach ([
                'avatar' => 'avatar_anterior',
                'fondo_cabecera' => 'cabecera_anterior',
                'fondo_general' => 'fondo_anterior',
            ] as $field => $previous) {
                $file = $_FILES[$field] ?? null;
                $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($error !== UPLOAD_ERR_NO_FILE && $error !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('La subida no se completó (código ' . $error . ').');
                }
                $images[$field] = $error === UPLOAD_ERR_OK
                    ? _file::save($file, "img/usuarios/$usuario->idu", '', false, 'original')
                    : (empty($post[$previous]) ? '' : $perfil_anterior->$field);
            }
        } catch (Throwable $e) {
            error_log('Profile image upload failed: ' . $e->getMessage());
            Session::setArray('toast', t('No se guardaron los cambios. No se pudo subir alguna imagen; comprueba que sea válida y no supere 16 MB.'));
            return false;
        }

        $avatar = $images['avatar'];
        $fondo_cabecera = $images['fondo_cabecera'];
        $fondo_general = $images['fondo_general'];

        if ($post['apodo'] <> $perfil_anterior->apodo) {
            (new Historico)->add('apodos', $perfil_anterior->apodo, $post['apodo']);
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
