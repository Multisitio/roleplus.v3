<?php
/**
 */
class AventurasController extends EvController
{
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
    public function index()
    {
        $this->aventuras = (new Aventuras)->todas();
    }

    #
    public function actualizar()
    {
        (new Aventuras)->actualizar($_POST);
    }

    #
    public function crear()
    {
        $aventuras_idu = (new Aventuras)->crear($_POST);
        Redirect::to('/ev/aventuras/formulario/' . $aventuras_idu);
    }

    #
    public function eliminar($aventuras_idu)
    {
        (new Aventuras)->eliminar($aventuras_idu);
        Redirect::to('/ev/aventuras');
    }

    #
    public function formulario($aventuras_idu='')
    {
        $this->fichas = (new Fichas)->todas();
        $this->aventura = (new Aventuras)->una($aventuras_idu);
        $this->aventuras_idu = $aventuras_idu;
    }
}
