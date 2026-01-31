<?php
/**
 */
class VistasPreviasController extends RegistradosController
{
    #
    public function eliminar($idu)
    {
        (new Vistas_previas)->eliminarVista($idu);
        View::select('');
    }
}
