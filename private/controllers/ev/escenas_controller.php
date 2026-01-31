<?php
/**
 */
class EscenasController extends EvController
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
    public function aventura($aventuras_idu)
    {
        $this->escenas = (new Escenas)->todas($aventuras_idu);
        $this->aventuras_idu = $aventuras_idu;
    }

    #
    public function actualizar()
    {
        (new Escenas)->actualizar($_POST);
    }

    #
    public function crear()
    {
        $escena = (new Escenas)->crear($_POST);
        Redirect::to("/ev/escenas/formulario/$escena->aventuras_idu/$escena->idu");
    }

    #
    public function elemento_actualizar()
    {
        $_POST['idu'] = $_POST['elementos_idu'];
        (new Elementos)->actualizar($_POST);
        Redirect::to("/ev/escenas/formulario/{$_POST['aventuras_idu']}/{$_POST['escenas_idu']}");
    }

    #
    public function elemento_crear()
    {
        (new Elementos)->crear($_POST);
        Redirect::to("/ev/escenas/formulario/{$_POST['aventuras_idu']}/{$_POST['escenas_idu']}");
    }

    #
    public function elemento_eliminar($aventuras_idu, $escenas_idu, $elementos_idu)
    {
        (new Elementos)->eliminar($elementos_idu);
        Redirect::to("/ev/escenas/formulario/$aventuras_idu/$escenas_idu");
    }

    #
    public function eliminar($aventuras_idu, $escenas_idu)
    {
        (new Escenas)->eliminar($escenas_idu);
        Redirect::to("/ev/escenas/aventura/$aventuras_idu");
    }

    #
    public function formulario($aventuras_idu, $escenas_idu='', $elementos_idu='')
    {
        $this->aventura = (new Aventuras)->una($aventuras_idu);
        $this->escena = (new Escenas)->una($escenas_idu);
        $this->elementos = (new Elementos)->todos($escenas_idu);
        $this->elemento = (new Elementos)->uno($elementos_idu);
        $this->tipos = (new Elementos)->tipos();
        $this->partida = (new Eventos)->partidaPorAventura($aventuras_idu);
    }
}
