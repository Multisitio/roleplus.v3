<?php
/**
 */
class ConversacionesController extends AtalayaController
{
    #
    public function index()
    {
        $this->conversaciones = (new Atalaya_conversaciones)->todas();
        $this->usuarios = (new Atalaya_conversaciones)->usuarios();
        $this->mensajes = (new Atalaya_conversaciones)->mensajes();
    }

    #
    public function eliminar($idu)
    {
        (new Atalaya_conversaciones)->eliminar($idu);
        View::select(null);
    }
}
