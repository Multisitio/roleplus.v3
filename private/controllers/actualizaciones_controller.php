<?php
/**
 */
class ActualizacionesController extends SocketController
{
    # SSE
    public function index($usuarios_idu='')
    {
        $this->usuarios_idu = $usuarios_idu;
        View::select('', 'sse');
    }

    #
    public function leer()
    {
        $this->rows = (new Actualizaciones)->leer();
        _var::die($this->rows);
        View::select('', '');
    }
}
