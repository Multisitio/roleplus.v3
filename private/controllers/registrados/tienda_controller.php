<?php
/**
 */
class TiendaController extends RegistradosController
{
    #
    public function index()
    {
        $this->usuario = (new Usuarios)->uno();
        $this->claves = (new Configuracion)->todas();
        $this->roles = Config::get('roles.singular'); 
    }

    #
    public function canjear_codigo()
    {
        (new Codigos)->canjearCodigo(Input::post());
        Redirect::to('/registrados/tienda');
    }

    #
    public function comprar_px($px)
    {
        (new Usuarios)->comprarPX($px);
        Redirect::to('/registrados/tienda');
    }

    #
    public function comprar_rol($rol)
    {
        (new Usuarios)->comprarRol($rol);
        Redirect::to('/registrados/tienda');
    }

    #
    public function chaman_pagado($periocidad='mensual')
    {
        (new Usuarios)->chamanPagado($periocidad);
        Redirect::to('/registrados/tienda');
    }
}
