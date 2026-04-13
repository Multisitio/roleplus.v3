<?php
require_once CORE_PATH . 'kumbia/controller.php';
/**
 */
class EvController extends GeneralController
{
	#
	final protected function initialize()
	{
		// 0) Filtro de seguridad JS
		$this->_filtrar_js_recursivo($_GET);
		$this->_filtrar_js_recursivo($_POST);

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
			Input::isAjax()
				? View::select('', 'login')
				: Redirect::to('/usuarios/formularios');
			return false;
		}

		if (Session::get('idu')) {
			(new Usuarios)->marcarAcceso();
		}

		$this->claves	= (new Configuracion)->todas();
		$this->usuario	= (new Usuarios)->uno();
        $this->version = '26';

		# CRÍTICO rendimiento:
		# liberamos el lock de la sesión aquí para que esta petición
		# no bloquee las siguientes del mismo usuario (mismo patrón que AppController).
		Session::close(); // session_write_close() si está abierta

		# Template según AJAX
		Input::isAjax()
			? View::template('ajax')
			: View::template('ev');
	}

	#
	final protected function finalize()
	{
	}
}
