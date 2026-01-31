<?php
/**
 */
class ConversacionesController extends RegistradosController
{
    #
    public function arreglo()
    {
        (new Conversaciones)->arreglo();
    }

    #
    public function enviar()
    {
        if (Input::post('contenido')) {
            (new Conversaciones_mensajes)->crear(Input::post());
        }
        View::select(null);
    }

    #
    public function leida($conversaciones_idu)
    {
        (new Conversaciones_usuarios)->marcarUltimoLeido($conversaciones_idu);
        #(new Conversaciones)->propagarListados($conversaciones_idu);
        View::select(null);
    }

    #
    public function listado()
    {
        $this->conversaciones = (new Conversaciones)->todasMisConversaciones();
    }

    #
    public function conversacion($conversaciones_idu)
    {
        $this->conversacion = (new Conversaciones)->una($conversaciones_idu);

        $this->mensajes = (new Conversaciones_mensajes)->todos(['conversaciones_idu'=>$conversaciones_idu]);

        $this->participantes = (new Conversaciones_usuarios)->participantes($conversaciones_idu);
        
        (new Conversaciones_usuarios)->marcarUltimoLeido($conversaciones_idu);

		(new Conversaciones)->propagarListados($conversaciones_idu);
    }

    #
    public function crear($usuarios_idu='')
    {
        $conversaciones_idu = (new Conversaciones)->crear($usuarios_idu);
        
        if (Input::isAjax()) {
            $this->conversacion($conversaciones_idu);
            return View::select('conversacion');
        }

		Redirect::to(parse_url($_SERVER['HTTP_REFERER'])['path'] . '/?conversaciones=' . $conversaciones_idu);
    }

    #
    public function recibir($conversaciones_mensajes_idu)
    {
        $this->men = (new Conversaciones_mensajes)->uno($conversaciones_mensajes_idu);

        View::select('mensaje');
    }

    #
    /*public function recibir($conversaciones_idu)
    {
        $this->mensajes = (new Conversaciones_mensajes)->todos([
            'conversaciones_idu'=>$conversaciones_idu, 
            'ultimo_fecha'=>date('Y-m-d H:i:s'),
            'accion'=>'recibir',
        ]);
        $this->abrir_chat = true;

        View::select('mensajes');
    }*/

    #
    public function sin_leer()
    {
        View::template(null);
    }

    #
    public function vaciar($conversaciones_idu)
    {
        (new Conversaciones_mensajes)->vaciar($conversaciones_idu);
        $this->mensajes = [];
        View::select('mensajes');
	}
}
