<?php
/**
 */
class EnlacesController extends RegistradosController
{
    #
	protected function before_filter()
	{
        if ($this->usuario->rol < 2) {
            Session::setArray('toast', t('Adquiera el rol Trampero.'));
            return Redirect::to('/registrados/tienda');
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
        $this->enlaces = (new Enlaces)->todos();
    }

    #
    public function acortar()
    {
        (new Enlaces)->acortar(Input::post());
        Redirect::to('/registrados/enlaces');
    }

    #
    public function eliminar($acortado)
    {
        (new Enlaces)->eliminar($acortado);
        Redirect::to('/registrados/enlaces');
    }
}
