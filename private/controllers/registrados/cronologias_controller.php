<?php
#
class CronologiasController extends RegistradosController
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
		Redirect::to('/registrados/cronologias/ver');
	}

	#
	public function ver()
	{
		$this->cronologias = (new Cronologias)->todas(Session::get('idu'));
		$this->cronologias_eventos = (new Cronologias_eventos)->todos();
		View::select('todas');
	}

	#
	public function cronologia($idu='')
	{
		$this->cronologia = (new Cronologias)->una($idu);
		View::template('ventana');
	}

    #
    public function actualizar()
    {
        (new Cronologias)->actualizar($_POST);
        Redirect::to('/registrados/cronologias/ver');
    }

    #
    public function crear()
    {
        (new Cronologias)->crear($_POST);
        Redirect::to('/registrados/cronologias/ver');
    }

    #
    public function eliminar()
    {
        (new Cronologias)->eliminar($_POST['idu']);
        Redirect::to('/registrados/cronologias/ver');
    }

    #
    public function maximizar($cronologia)
    {
		$this->cronologia = (new Cronologias)->una($cronologia);
		$this->eventos = (new Cronologias_eventos)->todos($cronologia);
    }

	#
	public function evento($cronologia, $evento='')
	{
		$this->cronologias_idu = $cronologia;
		$this->evento = (new Cronologias_eventos)->uno($evento);
		View::template('ventana');
	}

    #
    public function actualizar_evento()
    {
        (new Cronologias_eventos)->actualizar($_POST);
        Redirect::to('/registrados/cronologias/ver');
    }

    #
    public function crear_evento()
    {
        (new Cronologias_eventos)->crear($_POST);
        Redirect::to('/registrados/cronologias/ver');
    }

    #
    public function eliminar_evento()
    {
        (new Cronologias_eventos)->eliminar($_POST);
        Redirect::to('/registrados/cronologias/ver');
    }
}
