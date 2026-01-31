<?php
/**
 */
class TraduccionesController extends AtalayaController
{
    #
	protected function before_filter()
	{
        if ($action = Input::post('action')) {
            unset($_POST['action']);
            if (method_exists($this, $action)) {
                $this->$action();
            }
        }
    }

    #
    public function arreglo()
    {
        (new Traducciones)->arreglo();
        exit;
    }

    #
    public function index()
    {
        $this->idiomas = Config::get('combos.idiomas'); 

        $this->propuestas = (new Traducciones)->propuestas('pendientes');
    }

    #
    public function aceptar_propuesta()
    {
        (new Traducciones)->aceptarPropuesta(Input::post());
    }

    #
    public function rechazar_propuesta()
    {
        (new Traducciones)->rechazarPropuesta(Input::post());
    }

    #
    public function aplicar()
    {
        (new Traducciones)->volcarAFichero();
        Redirect::to('/atalaya/traducciones');
    }

    #
    public function obtener()
    {
        Redirect::to('/admin/translator');
    }
}
