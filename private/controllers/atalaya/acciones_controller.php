<?php
#
class AccionesController extends AtalayaController
{
    #
	protected function before_filter()
	{
        if ($action = Input::post('action')) {
            unset($_POST['action']);
            if ( method_exists($this, $action) ) {
                $this->$action();
            }
        }
    }

    #
    public function index()
    {
        $this->huerfanos = (new Atalaya_usuarios)->buscarRegistrosHuerfanos();
    }

    #
    public function borrar_huerfanos()
    {
        (new Atalaya_usuarios)->buscarRegistrosHuerfanos('borrar');
        Redirect::to('/atalaya/acciones');
    }

    #
    public function rescatar()
    {
        $this->inactivos = (new Atalaya)->enviarCorreoDeRescate();
        _var::die($this->inactivos);
    }

    #
    public function traducciones_desde_fichero()
    {
        (new Traducciones)->volcarABaseDeDatos();
        exit;
    }

    #
    public function traducciones_desde_basededatos()
    {
        (new Traducciones)->volcarAFichero();
        exit;
    }
}
