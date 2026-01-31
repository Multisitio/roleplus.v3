<?php
/**
 */
class GruposController extends AtalayaController
{
    #
    public function index($arreglar=0)
    {
        $this->grupos = (new Atalaya)->publicacionesPorGrupo($arreglar);
    }
}
