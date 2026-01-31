<?php
/**
 */
class EstadisticasController extends RegistradosController
{
    #
	protected function before_filter()
	{
        if ($this->usuario->rol < 2) {
            Session::setArray('toast', t('Adquiera el rol Trampero.'));
            return Redirect::to('/registrados/tienda');
        }
    }

    #
    public function index()
    {
        $this->comentarios = (new Estadisticas)->comentarios();

        $this->publicaciones = (new Estadisticas)->publicaciones();
    }
}
