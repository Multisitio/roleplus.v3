<?php
/**
 */
class IaController extends RegistradosController
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
    public function preguntar($idu='')
    {
        $this->respuesta = (new Respuestas)->una($idu);
        View::template('ventana');
    }

    #
    public function respuesta()
    {
        $this->respuesta = (new Respuestas)->responderPregunta(Input::post('frase'));
    }

    #
    public function respuestas()
    {
        $this->respuestas = (new Respuestas)->todas();
    }

    #
    public function editor($respuestas_idu='')
    {
        $this->respuesta = (new Respuestas)->una($respuestas_idu);
        View::template('ventana');
    }

    #
    public function actualizar()
    {
        if ($this->usuario->rol < 5) {
            Session::setArray('toast', t('Adquiera el rol Chamán.'));
            return Redirect::to('/registrados/tienda');
        }
        (new Respuestas)->actualizar($_POST);
        Redirect::to('/registrados/ia/respuestas');
    }

    #
    public function crear()
    {
        if ($this->usuario->rol < 5) {
            Session::setArray('toast', t('Adquiera el rol Chamán.'));
            return Redirect::to('/registrados/tienda');
        }
        (new Respuestas)->crear($_POST);
        Redirect::to('/registrados/ia/respuestas');
    }

    #
    public function eliminar()
    {
        if ($this->usuario->rol < 5) {
            Session::setArray('toast', t('Adquiera el rol Chamán.'));
            return Redirect::to('/registrados/tienda');
        }
        (new Respuestas)->eliminar($_POST['idu']);
        Redirect::to('/registrados/ia/respuestas');
    }
}
