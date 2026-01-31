<?php
/**
 */
class PartidasController extends EvController
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
        $this->partidas = ($this->claves['jugador_rol'] == 'master')
            ? (new Eventos)->todasMisPartidas()
            : (new Eventos)->todos('partidas');
    }

    #
    public function actualizar()
    {
        (new Eventos)->actualizar($_POST);
    }

    #
    public function chat_sin_leer($conversaciones_idu)
    {
        $this->sin_leer = (new Conversaciones_mensajes)->sinLeer($conversaciones_idu);
        View::template(null);
    }

    #
    public function crear()
    {
        $partidas_idu = (new Eventos)->crear('partidas', $_POST);
        Redirect::to('/ev/partidas/formulario/' . $partidas_idu);
    }

    #
    public function eliminar($partidas_idu)
    {
        $cat['idu'] = $partidas_idu;
        $cat['tipo'] = 'partidas';
        (new Eventos)->eliminar($cat);
        Redirect::to('/ev/partidas');
    }

    #
    public function formulario($partidas_idu='')
    {
        $this->aventuras = (new Aventuras)->todas();
        $this->partida = (new Eventos)->uno($partidas_idu);
        $this->partidas_idu = $partidas_idu;
    }

    #
    public function mantener_conexion($partidas_idu)
    {
        (new Partidas_usuarios)->conectar($partidas_idu);
        View::select('', '');
    }
}
