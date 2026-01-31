<?php
/**
 */
class EnlacesController extends AppController
{
    #
    public function ir($acortado)
    {
        $enlaces = (new Enlaces)->sumarUno($acortado);
        header("Location: $enlaces->enlace");
        exit;
    }
}
