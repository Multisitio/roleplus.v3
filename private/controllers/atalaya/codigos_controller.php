<?php
/**
 */
class CodigosController extends AtalayaController
{
    #
    public function index()
    {
        $this->codigos = (new Codigos)->todos();
    }

    #
    public function crear_codigo()
    {
        (new Codigos)->crearCodigo(Input::post());
        Redirect::to('/atalaya/codigos');
    }
}
