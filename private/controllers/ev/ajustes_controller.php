<?php
/**
 */
class AjustesController extends EvController
{
    #
    public function index()
    {
    }

    #
    public function guardar($clave, $valor)
    {
        (new Configuracion)->guardar($clave, $valor);
        Redirect::to('/ev');
    }
}
