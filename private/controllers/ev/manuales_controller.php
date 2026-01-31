<?php
/**
 */
class ManualesController extends EvController
{
    #
	protected function before_filter()
	{
        if ($this->usuario->rol < 5) {
            Session::setArray('toast', t('Adquiera el rol Chamán.'));
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
    public function index($manuales_idu='')
    {
        $this->manuales = (new Manuales)->todos($manuales_idu);
    }

    #
    public function formulario($manuales_idu='')
    {
        $this->manual = (new Manuales)->uno($manuales_idu);
        $this->reglas = (new Manuales_reglas)->todas($manuales_idu);
    }

    #
    public function formulario_manual($manuales_idu='')
    {
        $this->manual = (new Manuales)->uno($manuales_idu);
        $this->fichas = (new Fichas)->todas();
    }

    #
    public function ver($manuales_idu)
    {
        $this->manual = (new Manuales)->uno($manuales_idu);
        $this->title = $this->manual->nombre;
        $this->reglas = (new Manuales_reglas)->todas($manuales_idu);
        View::template('impresion');
    }

    #
    public function actualizar()
    {
        $manuales_idu = (new Manuales)->actualizar($_POST);
        Redirect::to('/ev/manuales/formulario/' . $manuales_idu);
    }

    #
    public function crear()
    {
        $manuales_idu = (new Manuales)->crear($_POST);
        Redirect::to('/ev/manuales/formulario/' . $manuales_idu);
    }

    #
    /*public function duplicar()
    {
        $manuales_idu = (new Manuales)->duplicar($_POST);
        Redirect::to('/ev/manuales/formulario/' . $manuales_idu);
    }*/

    #
    public function eliminar()
    {
        (new Manuales)->eliminar($_POST['idu']);
        Redirect::to('/ev/manuales/formulario');
    }

    # Acciones para manejar las reglas:

    #
    public function formulario_regla($manuales_idu, $manuales_reglas_idu='')
    {
        $this->manual = (new Manuales)->uno($manuales_idu);
        $this->manuales_idu = $this->manual->idu;
        $this->reglas = (new Manuales_reglas)->todas($manuales_idu);
        $this->regla = (new Manuales_reglas)->una($manuales_reglas_idu);
    }

    #
    public function regla_actualizar()
    {
        $this->reglas_idu = (new Manuales_reglas)->actualizar($_POST);
        Redirect::to(parse_url($_SERVER['HTTP_REFERER'])['path']);
    }

    #
    public function regla_crear()
    {
        $this->reglas_idu = (new Manuales_reglas)->crear($_POST);
        Redirect::to(parse_url($_SERVER['HTTP_REFERER'])['path']);
    }

    #
    public function regla_eliminar()
    {
        (new Manuales_reglas)->eliminar($_POST['idu']);
        Redirect::to(parse_url($_SERVER['HTTP_REFERER'])['path']);
    }

    #
    public function regla_traducir()
    {
        (new Manuales_reglas)->crear($_POST, $_POST['traducir_al']);
        Redirect::to(parse_url($_SERVER['HTTP_REFERER'])['path']);
    }

    # 
    public function editor($idioma, $manuales_idu)
    {
        $this->manual = (new Manuales)->uno($manuales_idu);
        $this->reglas = (new Manuales_reglas)->todas($manuales_idu, $idioma);
        $this->manuales_idu = $manuales_idu;
        View::template('editor');
    }

    #
    public function plan_de_accion()
    {
        View::template(null);
    }
}
