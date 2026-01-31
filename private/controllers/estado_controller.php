<?php
/**
 */
class EstadoController extends AppController
{
    #
    public function index()
    {
        $this->claves = (new Configuracion)->todas();
        $this->usuario = (new Usuarios)->uno();
    }
}
