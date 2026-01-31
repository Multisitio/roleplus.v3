<?php
/**
 */
class ArreglosController extends AtalayaController
{
    #
    public function index()
    {
        $this->vistas_previas = (new Atalaya_vistas_previas)->todas();
        $this->en_comentarios = (new Atalaya_vistas_previas)->enComentarios();
        $this->en_publicaciones = (new Atalaya_vistas_previas)->enPublicaciones();
        $this->huerfanas = (new Atalaya_vistas_previas)->huerfanas();
    }

    #
    public function vistas_previas($limpiar=0, $asignar_usuario=0)
    {
        (new Atalaya_vistas_previas)->huerfanas($limpiar, $asignar_usuario);
        Redirect::to('/atalaya/arreglos');
    }
}
