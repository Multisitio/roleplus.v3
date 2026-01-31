<?php
require_once CORE_PATH . 'kumbia/controller.php';
#
class AtalayaController extends Controller
{
    #
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

    #
    final protected function finalize()
    {
    }
}
