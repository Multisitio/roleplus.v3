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
        try {
            $uno = (new Personajes_usuarios)->uno($personajes_idu);
            empty($uno)
                ? (new Personajes_usuarios)->vincular($fichas_idu, $personajes_idu)
                : (new Personajes_usuarios)->desvincular($personajes_idu);
        } catch (RuntimeException $e) {
            Session::setArray('toast', t($e->getMessage()));
        }

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
            echo json_encode(['success'=>true, 'url'=>'/ev/personajes/montar/' . Input::post('fichas_idu') . '/' . $idu]
                + (new Personajes)->estado(Input::post('fichas_idu'), $idu));
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
        // Legacy tabs can implicitly submit their first button (Eliminar) on Enter.
        if ( ! Input::post('confirmar_eliminacion') || Input::post('confirmar_eliminacion') !== $_POST['personajes_idu']) {
            http_response_code(400);
            View::select(null);
            View::template(null);
            echo 'El personaje no se ha eliminado: falta confirmar expresamente la eliminación.';
            return;
        }

        (new Personajes)->eliminar($_POST['personajes_idu']);

        if (Input::isAjax()) {
            View::select(null);
            View::template(null);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success'=>true, 'url'=>'/ev/personajes/montados/' . Input::post('fichas_idu')]);
            return;
        }

        if (Input::post('fichas_idu')) {
            return Redirect::to("/ev/personajes/montar/" .  Input::post('fichas_idu'));
        }
        Redirect::to('/ev/personajes');
    }

    # https://roleplus.app/ev/personajes/montar/588bc2f2187e/565bead0eb4a
    public function montar($fichas_idu, $personajes_idu='')
    {
        if ($personajes_idu) {
            try { (new Personajes)->estado($fichas_idu, $personajes_idu); }
            catch (RuntimeException $e) {
                if ($e->getCode() !== 404) { throw $e; }
                Session::setArray('toast', t('Este personaje no está disponible.'));
                return Redirect::to('/ev/personajes/montados/' . $fichas_idu);
            }
        }
        $this->ficha = (new Fichas)->una($fichas_idu);
        $this->cajas = (new Fichas_cajas)->todas($this->ficha->idu);
        $this->personaje = (new Personajes)->uno($this->ficha->idu, $personajes_idu);
        $this->personajes_usuarios = (new Personajes_usuarios)->uno($personajes_idu);
        $this->puede_salvar = Session::get('idu') && ( ! $personajes_idu
            || (new Personajes)->esPropietario($personajes_idu));
        $this->puede_eliminar = $personajes_idu && $this->puede_salvar;
        $this->idu = $personajes_idu ?: _str::uid();
        $this->estado_inicial = $personajes_idu ? (new Personajes)->estado($fichas_idu, $personajes_idu)
            : ['idu'=>$this->idu, 'revision'=>0, 'datos'=>$this->personaje, 'editable'=>(bool)$this->puede_salvar];
        $this->estado_inicial['sincronizar'] = (bool)Session::get('idu');
        View::template('fichas');
    }

    public function estado($fichas_idu, $personajes_idu)
    {
        View::select(null);
        View::template(null);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        try {
            if ( ! Session::get('idu')) { throw new RuntimeException('Sesión ausente.', 401); }
            echo json_encode(['success'=>true] + (new Personajes)->estado($fichas_idu,$personajes_idu));
        } catch (Throwable $e) {
            http_response_code(in_array($e->getCode(),[401,403,404],true) ? $e->getCode() : 500);
            echo json_encode(['success'=>false]);
        }
    }
}
