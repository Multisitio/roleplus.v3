<?php
/**
 */
class ConsejosController extends AppController
{
    #
    public function contabilizar($idu)
    {
        $consejo = (new Publicidad)->uno($idu);
        (new Publicidad)->contabilizar($idu, 'visitada');
        header('Location: ' . $consejo->url);
        exit();
    }
}
