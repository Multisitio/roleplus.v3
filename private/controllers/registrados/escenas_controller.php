<?php
/**
 */
class EscenasController extends RegistradosController
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
    public function aventura($aventuras_idu, $peso='')
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

        $this->escenas = (new Escenas)
            ->where('aventuras_idu=?', [$aventuras_idu])
            ->order('peso')
            ->rows();

        $this->caminos = (new Caminos)->byParents($this->escenas, 'escenas_idu');
    }

    # 2
    public function formulario($aventuras_idu, $escenas_idu='')
    {
        $this->aventura = (new Aventuras)
            ->where('idu=?', [$aventuras_idu])
            ->row();

        $this->escena = (new Escenas)
            ->where('idu=?', [$escenas_idu])
            ->row();

        $this->caminos = (new Caminos)
            ->where('escenas_idu=?', [$escenas_idu])
            ->rows();

        View::template('ventana');
    }

    # 3
    public function crear()
    {
        $_POST['escenas_idu'] = (new Escenas)->crear(Input::post())->idu;

        (new Caminos)->guardar(Input::post());

        Redirect::to('/registrados/escenas/aventura/' . Input::post('aventuras_idu'));
    }

    # 4
    public function actualizar()
    {
        (new Caminos)->guardar(Input::post());

        (new Escenas)->actualizar(Input::post());
        
        Redirect::to('/registrados/escenas/aventura/' . Input::post('aventuras_idu'));
    }
}
