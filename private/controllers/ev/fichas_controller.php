<?php
/**
 */
class FichasController extends EvController
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
        $this->fichas = (new Fichas)->todas();
    }

    #
    public function actualizar()
    {
        if (Input::post()) {
            $this->fichas_idu = (new Fichas)->actualizar($_POST);
        }
    }

    #
    public function crear()
    {
        $fichas_idu = (new Fichas)->crear($_POST);
        Redirect::to("ev/fichas/formulario/$fichas_idu");
    }

    #
    public function eliminar($fichas_idu='')
    {
        $_POST['fichas_idu'] = empty($fichas_idu) ? Input::post('fichas_idu') : $fichas_idu;
        (new Fichas)->eliminar($_POST['fichas_idu']);
        if ($fichas_idu) {
            Redirect::to("ev/fichas");
        }
    }

    #
    public function caja_actualizar()
    {
        (new Fichas_cajas)->actualizar($_POST);
    }

    #
    public function caja_cargar($caja_nombre, $cajas_idu='')
    {
        $this->caja = (new Fichas_cajas)->una($cajas_idu);
        $tipos = Config::get('vistas.cajas'); 
        $caja_nombre = empty($tipos[$caja_nombre]) ? 'campo_de_texto' : $caja_nombre;
        View::select("cajas/$caja_nombre");
    }

    #
    public function caja_crear()
    {
        (new Fichas_cajas)->crear($_POST);
    }

    #
    public function caja_eliminar()
    {
        (new Fichas_cajas)->eliminar($_POST['idu']);
    }

    #
    public function caja_nueva($fichas_idu, $cajas_idu='')
    {
        $this->ficha = (new Fichas)->una($fichas_idu);
        $this->caja = (new Fichas_cajas)->una($cajas_idu);
        $this->caja_tipo = empty($this->caja->tipo) ? 'campo_de_texto' : $this->caja->tipo;
        $this->cajas_padre = (new Fichas_cajas)->todasPadres($fichas_idu);
        $this->tipos = Config::get('vistas.cajas'); 
        View::template('');
    }

    #
    public function cajas($fichas_idu)
    {
        $this->ficha = (new Fichas)->una($fichas_idu);
        $this->cajas = (new Fichas_cajas)->todas($fichas_idu);
        View::template('fichas');
    }

    #
    public function formulario($fichas_idu='')
    {
        $this->ficha = (new Fichas)->una($fichas_idu);
        $this->fichas = (new Fichas)->todas();
    }
}
