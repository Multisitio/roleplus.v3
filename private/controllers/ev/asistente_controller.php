<?php
/**
 */
class AsistenteController extends EvController
{
    #
    public function jugadores()
    {
    }

    #
    public function fichas()
    {
        (new Configuracion)->guardar('jugador_rol', 'master');
        $this->fichas = (new Fichas)->todas();
        $this->fichas_idu = (new Configuracion)->valor('fichas_idu');
    }

    #
    public function personajes()
    {
        if (Input::post('fichas_idu')) {
            (new Configuracion)->guardar('fichas_idu', Input::post('fichas_idu'));
        }
        $this->fichas_idu = (new Configuracion)->valor('fichas_idu');
        if ( ! $this->fichas_idu) {
            Session::setArray('toast', t('No se ha escogido una ficha.'));
            Redirect::to('/ev/asistente/fichas');
        }
    }

    #
    public function aventuras()
    {
        $this->aventuras = (new Aventuras)->todas();
        $this->aventuras_idu = (new Configuracion)->valor('aventuras_idu');
    }

    #
    public function escenas()
    {
        if (Input::post('aventuras_idu')) {
            (new Configuracion)->guardar('aventuras_idu', Input::post('aventuras_idu'));
        }
        $this->aventuras_idu = (new Configuracion)->valor('aventuras_idu');
        if ( ! $this->aventuras_idu) {
            Session::setArray('toast', t('No se ha escogido una aventura.'));
            Redirect::to('/ev/asistente/aventuras');
        }
        $this->escenas = (new Escenas)->todas($this->aventuras_idu);
        $this->escenas_idu = (new Configuracion)->valor('escenas_idu');
    }

    #
    public function escoge_partida()
    {
    }

    #
    public function elementos()
    {
        if (Input::post('escenas_idu')) {
            (new Configuracion)->guardar('escenas_idu', Input::post('escenas_idu'));
        }
        $this->escenas_idu = (new Configuracion)->valor('escenas_idu');
        if ( ! $this->escenas_idu) {
            Session::setArray('toast', t('No se ha escogido una escena.'));
            Redirect::to('/ev/asistente/escenas');
        }
        $this->aventuras_idu = (new Configuracion)->valor('aventuras_idu');
        $this->elementos = (new Escenas)->todas($this->escenas_idu);
        $this->elementos_idu = (new Configuracion)->valor('elementos_idu');
    }

    #
    public function partidas()
    {
        (new Configuracion)->guardar('jugador_rol', 'jugador');
        $this->partidas = (new Eventos)->todasMisPartidas();
        $this->partidas_idu = (new Configuracion)->valor('partidas_idu');
    }

    #
    public function panel()
    {
        if (Input::post('partidas_idu')) {
            (new Configuracion)->guardar('partidas_idu', Input::post('partidas_idu'));
        }
        $this->partidas_idu = (new Configuracion)->valor('partidas_idu');
        if ( ! $this->partidas_idu) {
            Session::setArray('toast', t('No se ha escogido una partida.'));
            Redirect::to('/ev/asistente/partidas');
        }
    }
}
