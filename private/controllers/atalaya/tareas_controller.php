<?php
/**
 */
class TareasController extends AtalayaController
{
    #
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
        Redirect::to('/atalaya/tareas/listar');
    }
    
    #
    public function listar($estado='', $tareas_idu='')
    {
        $this->tareas = empty($tareas_idu)
            ? (new Tareas)->propuestas()
            : (new Tareas)->propuestas($estado, $tareas_idu);
            
        $this->estado = $estado;
    }

    #
    public function aprobar()
    {
        $_POST['estado'] = 'aprobada';
        (new Tareas)->cambiarEstado(Input::post());
    }

    #
    public function posponer()
    {
        $_POST['estado'] = 'pospuesta';
        (new Tareas)->cambiarEstado(Input::post());
    }

    #
    public function rechazar()
    {
        $_POST['estado'] = 'rechazada';
        (new Tareas)->cambiarEstado(Input::post());
    }

    #
    public function terminar()
    {
        $_POST['estado'] = 'terminada';
        (new Tareas)->cambiarEstado(Input::post());
    }

    #
    public function enviar_comentario()
    {
        (new Tareas)->comentarPropuesta(Input::post());
    }
}
