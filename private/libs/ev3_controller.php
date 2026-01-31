<?php
require_once CORE_PATH . 'kumbia/controller.php';
/**
 */
class Ev3Controller extends Controller
{
    #
    final protected function initialize()
    {
        if ( ! Session::get('idu')) {
            (new Usuarios)->entrarSiGalleta();
        }
        
        # Acciones permitidas sin login
        if ( ! Session::get('idu') && preg_match('/(borrarse|identificarse|registrarse|recordar_clave)/', Input::post('action'))) {
            $action = Input::post('action');
            (new Usuarios)->$action($_POST);
        }

        # IF NOT session THEN login form
        if ( ! Session::get('idu')) {
            Input::isAjax() ? View::select('', 'login') : Redirect::to('/usuarios/formularios');
            return false;
        }

        if (Session::get('idu')) {
            (new Usuarios)->marcarAcceso();
        }

        $this->claves = (new Configuracion)->todas();
        $this->usuario = (new Usuarios)->uno();
		$this->version = '250415';
        Input::isAjax() ? View::template('ajax') : View::template('ev3');
    }

    #
    final protected function finalize()
    {
    }
}
