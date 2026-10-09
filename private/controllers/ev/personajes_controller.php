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
                return false;
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
        if (Input::isAjax()) {
            View::select(null);
            View::template(null);
            header('Content-Type: application/json; charset=UTF-8');
        }
        try {
            $idu = (new Personajes)->salvar($_POST);
        } catch (Throwable $e) {
            if ( ! Input::isAjax()) {
                throw $e;
            }
            http_response_code($e->getCode() >= 400 && $e->getCode() <= 599 ? $e->getCode() : 500);
            echo json_encode(['success' => false, 'error' => 'No se ha podido guardar el personaje. Comprueba tu sesión y vuelve a intentarlo.']);
            return;
        }
        if (Input::isAjax()) {
            echo json_encode(['success' => true, 'idu' => $idu,
                'url' => '/ev/personajes/montar/' . Input::post('fichas_idu') . '/' . $idu]);
            return;
        }
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
        $this->puede_salvar = Session::get('idu') && ( ! $personajes_idu
            || (new Personajes)->esPropietario($personajes_idu));
        $this->puede_eliminar = $personajes_idu && $this->puede_salvar;
        $this->idu = $personajes_idu ?: _str::uid();
        View::template('fichas');
    }
}
