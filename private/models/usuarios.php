<?php
require_once VENDOR_PATH . 'autoload.php';
require_once APP_PATH . 'models/usuarios/trait_admin.php';
require_once APP_PATH . 'models/usuarios/trait_google.php';
require_once APP_PATH . 'models/usuarios/trait_perfil.php';
require_once APP_PATH . 'models/usuarios/trait_registro.php';
require_once APP_PATH . 'models/usuarios/trait_sesion.php';
require_once APP_PATH . 'models/usuarios/trait_tienda.php';
/**
 */
class Usuarios extends LiteRecord
{
    use UsuariosAdmin;
    use UsuariosGoogle;
    use UsuariosPerfil;
    use UsuariosRegistro;
    use UsuariosSesion;
    use UsuariosTienda;
}
