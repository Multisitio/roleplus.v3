<?php
/**
 */
class UsuariosController extends RegistradosController
{
    #
    public function actualizar($campo, $valor='')
    {
        $valor = ($s = Input::post('valor')) ? $s : $valor;
        $this->valor = (new Usuarios)->actualizar($campo, $valor);
        if ( ! Input::isAjax()) {
            Redirect::to(parse_url($_SERVER['HTTP_REFERER'])['path']);
        }
    }

    #
    public function avatar($archivo='')
    {
        /*$name = empty($_FILES)
            ? (new Archivos)->foto2avatar($archivo)
            : (new Archivos)->incluir($_FILES, 'usuarios/' . Session::get('idu'));*/

        $name = (new Archivos)->incluir($_FILES, 'usuarios/' . Session::get('idu'));

        #(new Archivos)->foto2avatar($name);
        
#_var::die($name);
        (new Usuarios)->actualizar('avatar', $name);
        
		Redirect::to(parse_url($_SERVER['HTTP_REFERER'])['path']);
    }

    #
    public function borrarse()
    { 
        (new Usuarios)->borrarse();
        Redirect::to('/');
    }

    #
    public function editar()
    {
        $this->usuario = (new Usuarios)->uno();
        View::template('ventana');
    }

    #
    public function bloquear($idu)
    {
        $this->idu = (new Acciones)->alternar($idu, 'usuarios', 'bloqueado');   

        if (Input::isAjax()) {   
            $this->bloqueados = (new Acciones)->registros('bloqueado');
            View::select('seguir');
        }
        else {
            Redirect::to(parse_url($_SERVER['HTTP_REFERER'])['path']);
        }
    }
    
    #
    public function notificar($idu)
    {
        $this->usu = (new Usuarios)->uno($idu);
        if ((new Acciones)->alternar($idu, 'usuarios', 'notificar')) {
            Session::setArray('toast', t('Cuando publique este perfil recibirás una notificación.'));
            (new Notificaciones)->siguiendo($this->usu);
        }
        else {
            Session::setArray('toast', t('Perfil silenciado.'));
        }

        (new Usuarios)->recalcularAtentos($idu);

        if ( ! Input::isAjax()) {
            Redirect::to(parse_url($_SERVER['HTTP_REFERER'])['path']);
        }
        $this->notificando = (new Acciones)->registrosPorElementoYAccion('usuarios', 'notificar');
    }

    /*
	protected function before_filter()
	{
        if ($action = Input::post('action'))
        {
            unset($_POST['action']);
            if ( method_exists($this, $action) ) $this->$action();
        }
    }

    #
    public function conectado_ev()
    {
        (new Usuarios)->marcarAcceso('.conectados', '/ev/escritorio/conectados');
        View::select('', '');
    }

    # De momento solo se usa desde el correo de rescate
    public function borrar($email, $clave)
    {
        (new Usuarios)->borrar_usuario(['email'=>$email, 'clave'=>base64_decode($clave)]);
        Redirect::to('/');
    }

    #
    public function entrar()
    {
        $url = parse_url($_SERVER['HTTP_REFERER'])['path'];
        $url = ($url ==='/registrados/usuarios/entrar') ? '/' : $url;
        Redirect::to($url);
    }

    #
    public function transferir_px()
    {
        (new Usuarios)->transferirPX($_POST);
        Redirect::to(parse_url($_SERVER['HTTP_REFERER'])['path']);
    }*/
}
