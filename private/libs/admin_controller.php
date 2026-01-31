<?php
/**
 * @see Controller nuevo controller
 */
require_once CORE_PATH . 'kumbia/controller.php';

/**
 * Controlador para proteger los controladores que heredan
 * Para empezar a crear una convención de seguridad y módulos
 *
 * Todas las controladores heredan de esta clase en un nivel superior
 * por lo tanto los métodos aquí definidos estan disponibles para
 * cualquier controlador.
 *
 * @category Kumbia
 * @package Controller
 */
abstract class AdminController extends Controller
{

    final protected function initialize()
    {
        if ( ! Session::get('idu')) {
            (new Usuarios)->entrarSiGalleta();
        }

        $this->usuario = (new Usuarios)->uno(Session::get('idu'));
        
        if ($this->usuario->rol < 6) {
            return Redirect::to('/publicaciones');
        }
                
        $this->claves = (new Configuracion)->todas();
        Input::isAjax() ? View::template('ajax') : View::template('atalaya');

		ini_set('max_execution_time', 1200);
    }

    final protected function finalize()
    {
        
    }

}
