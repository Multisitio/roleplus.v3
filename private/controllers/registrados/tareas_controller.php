<?php
/**
 */
class TareasController extends RegistradosController
{
    #
	protected function before_filter()
	{
        /*if ($this->usuario->rol < 3) {
            Session::setArray('toast', t('Esta sección es para el rol Portaestandarte o superior.'));
            Redirect::to('/registrados/tienda');
            return false;
        }*/

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
        Redirect::to('/registrados/tareas/listar');
    }
    
    #
    public function listar($estado='', $tareas_idu='')
    {
        $this->tareas = empty($tareas_idu)
            ? (new Tareas)->propuestas($estado)
            : (new Tareas)->propuestas($estado, $tareas_idu);

        $this->estado = $estado;
    }
    
    #
    public function enviar_propuesta()
    {
        (new Tareas)->enviarPropuesta(Input::post());
    }
    
    #
    public function enviar_comentario()
    {
        (new Tareas)->comentarPropuesta(Input::post());
    }
}
