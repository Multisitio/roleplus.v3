<?php
/**
 */
class AccesosController extends AppController
{
    #
    public function desbloquear()
    {
        (new Accesos)->desbloquear();
        Redirect::to('/');
    }
}
