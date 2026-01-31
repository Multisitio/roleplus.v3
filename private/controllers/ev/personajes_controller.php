<?php
/**
 */
class PersonajesController extends EvController
{
	protected function before_filter()
	{
        if ($action = Input::post('action')) {
            unset($_POST['action']);
            if (method_exists($this, $action)) {
                $this->$action();
            }
        }
    }

    #
    public function montados($fichas_idu)
    {
        $this->ficha = (new Fichas)->una($fichas_idu);
        $this->personajes = (new Personajes)->todos($fichas_idu);
        $this->personajes_usuarios = (new Personajes_usuarios)->todos($fichas_idu);
    }

    #
    public function vincular($fichas_idu, $personajes_idu)
    {
        $uno = (new Personajes_usuarios)->uno($personajes_idu);
        empty($uno)
            ? (new Personajes_usuarios)->vincular($fichas_idu, $personajes_idu)
            : (new Personajes_usuarios)->desvincular($personajes_idu);

        Redirect::to("/ev/personajes/montados/$fichas_idu#$personajes_idu");
    }

    #
    public function salvar()
    {
        $idu = (new Personajes)->salvar($_POST);
        return Redirect::to("ev/personajes/montar/" . Input::post('fichas_idu') . "/$idu");
    }

    #
    public function duplicar()
    {
        $idu = (new Personajes)->duplicar($_POST);
        return Redirect::to("ev/personajes/montar/" . Input::post('fichas_idu') . "/$idu");
    }

    #
    public function eliminar($personajes_idu='')
    {
        $_POST['personajes_idu'] = empty($personajes_idu) ? Input::post('idu') : $personajes_idu;

        (new Personajes)->eliminar($_POST['personajes_idu']);

        if (Input::post('fichas_idu')) {
            return Redirect::to("/ev/personajes/montar/" .  Input::post('fichas_idu'));
        }
        Redirect::to('/ev/personajes');
    }

    # https://roleplus.app/ev/personajes/montar/588bc2f2187e/565bead0eb4a
    public function montar($fichas_idu, $personajes_idu='')
    {
        $this->ficha = (new Fichas)->una($fichas_idu);
        $this->cajas = (new Fichas_cajas)->todas($this->ficha->idu);
        $this->personaje = (new Personajes)->uno($this->ficha->idu, $personajes_idu);
        $this->personajes_usuarios = (new Personajes_usuarios)->uno($personajes_idu);
        $this->idu = $personajes_idu;
        View::template('fichas');
    }
}
