<?php
/**
 */
class ArchivosController extends AtalayaController
{
    #
    public function index()
    {
    }

    #
    public function borrar_carpetas_de_imagenes_huerfanas($accion='')
    {
        $this->carpetas = (new Atalaya_archivos)->carpetasHuerfanas($accion);
        View::select('carpetas');
    }

    #
    public function borrar_imagenes_huerfanas_en_vistas_previas($accion='')
    {
        $this->carpetas = (new Atalaya_archivos)->vistasHuerfanas($accion);
        View::select('vistas_previas');
    }
}
