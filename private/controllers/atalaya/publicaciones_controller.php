<?php
/**
 */
class PublicacionesController extends AtalayaController
{
    #
    public function generar_slugs()
    {
        ini_set('memory_limit', '4096M');
        #(new Publicaciones)->emptyField('slug');
        (new Publicaciones)->genSlugs('titulo', 'contenido');
        exit();
    }
}
