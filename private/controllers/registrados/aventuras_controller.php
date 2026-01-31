<?php
/**
 */
class AventurasController extends RegistradosController
{
    # 0
	protected function before_filter()
	{
        if ($action = Input::post('action')) {
            unset($_POST['action']);
            if (method_exists($this, $action)) {
                $this->$action();
            }
        }
    }

    # 1
    public function index()
    {
        $this->aventuras = (new Aventuras)
            ->where('creada>?', ['2023-01-31'])
            ->rows();

        #_var::die($this->aventuras);
    }

    # 2
    public function formulario($aventuras_idu='')
    {
        $this->aventura = (new Aventuras)
            ->where('idu=?', [$aventuras_idu])
            ->row();

        View::template('ventana');
    }

    # 3
    public function crear()
    {
        (new Aventuras)->crear(Input::post());
        Redirect::to('/registrados/aventuras');
    }

    # 4
    public function actualizar()
    {
        (new Aventuras)->actualizar(Input::post());
        Redirect::to('/registrados/aventuras');
    }

    # 5
    public function eliminar($aventuras_idu)
    {
        (new Aventuras)->eliminar($aventuras_idu);
        Redirect::to('/registrados/aventuras');
    }

    # 6
    public function jugar($aventuras_idu, $peso='')
    {
        $this->aventura = (new Aventuras)
            ->where('idu=?', [$aventuras_idu])
            ->row();

        if ($peso) {
            $this->escena = (new Escenas)
                ->where('aventuras_idu=? AND peso=?', [$aventuras_idu, $peso])
                ->row();
        }
        else {
            $this->escena = (new Escenas)
                ->where('aventuras_idu=?', [$aventuras_idu])
                ->order('peso')
                ->row();
        }

        $this->caminos = (new Caminos)
            ->where('escenas_idu=?', [$this->escena->idu])
            ->rows();

        View::template('jugar');
    }
}
