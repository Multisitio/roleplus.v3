<?php
/**
 */
class TraduccionesController extends RegistradosController
{
    #
	protected function before_filter()
	{
        if ($this->usuario->rol < 3) {
            Session::setArray('toast', t('Esta sección es para el rol Portaestandarte o superior.'));
            Redirect::to('/registrados/tienda');
            return false;
        }

        if ($action = Input::post('action')) {
            unset($_POST['action']);
            if (method_exists($this, $action)) {
                $this->$action();
            }
        }
    }
    
    #
    public function index()
    {
        Redirect::to('/registrados/traducciones/listar/pendientes');
    }
    
    #
    public function listar($estado='pendientes')
    {
        $this->idiomas = Config::get('combos.idiomas'); 

        $this->traducciones = Input::post()
            ? (new Traducciones)->buscar(Input::post())
            : [];

        $this->propuestas = (new Traducciones)->propuestas($estado);

        $this->estado = $estado;
    }
    
    #
    public function enviar_propuesta()
    {
        (new Traducciones)->enviarPropuesta(Input::post());
    }
}
