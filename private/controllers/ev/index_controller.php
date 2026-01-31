<?php
/**
 */
class IndexController extends EvController
{
    #
    public function index()
    {
        Redirect::to('/ev3');

        /*$this->roles_de_jugador = (new Eventos)->rolesDeJugador();

        # IF NOT rol de jugador THEN ...
        if (empty($this->claves['jugador_rol'])) {
            View::select('jugador_rol');
        }  

        # IF rol de jugador THEN ...
        if ($this->claves['jugador_rol'] == 'jugador') {
            Redirect::to('/ev/partidas');
        }  
        elseif ($this->claves['jugador_rol'] == 'master') {
            Redirect::to('/ev/partidas');
        }*/
    }
}
