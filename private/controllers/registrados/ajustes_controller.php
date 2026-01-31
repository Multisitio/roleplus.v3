<?php
/**
 */
class AjustesController extends RegistradosController
{
    #
    public function index()
    {
        if ( ! empty($_GET['no-cache'])) {
            header("Cache-Control: no-cache, must-revalidate");
            header("Expires: Sat, 1 Jul 2000 05:00:00 GMT");
        }

        $this->suscripciones = (new Suscripciones)->todasPorUsuario(Session::get('idu'));
        
        $this->idiomas = Config::get('combos.idiomas'); 
    }

    #
    public function alternar($clave, $valor)
    {
        if (preg_match('/color|tema/', $clave)) {

        }
        (new Configuracion)->alternar($clave, $valor);
        View::select('');
    }

    #
    public function eliminar_suscripcion($id)
    {
        (new Suscripciones)->eliminarSuscripcion($id);
        Redirect::to(parse_url($_SERVER['HTTP_REFERER'])['path']);
    }

    #
    public function guardar($clave, $valor)
    {
        if (preg_match('/color|tema|jugador_rol|fichas_idu|aventuras_idu|escenas_idu|partidas_idu/', $clave)) {
            (new Configuracion)->guardar($clave, $valor);
        }
        Redirect::to(parse_url($_SERVER['HTTP_REFERER'])['path']);
    }

    #
    public function no_al_boletin()
    {
        $this->alternar('no_al_boletin', 1);
        Session::setArray('toast', t('Cambio aplicado.'));
        Redirect::to('/registrados/ajustes');
    }

    #
    public function no_notificar_masuno()
    {
        if ($this->usuario->rol < 2) {
            Session::setArray('toast', t('Adquiera el rol Trampero.'));
            return Redirect::to('/registrados/tienda');
        }
        $this->alternar('no_notificar_masuno', 1);
        Session::setArray('toast', t('Cambio aplicado.'));
        Redirect::to('/registrados/ajustes');
    }

    #
    public function quitar_masonry()
    {
        $this->alternar('quitar_masonry', 1);
        Session::setArray('toast', t('Cambio aplicado.'));
        Redirect::to('/registrados/ajustes');
    }

    #
    public function quitar_publicidad()
    {
        if ($this->usuario->rol < 5) {
            Session::setArray('toast', t('Adquiera el rol Chamán.'));
            return Redirect::to('/registrados/tienda');
        }
        $this->alternar('quitar_publicidad', 1);
        Session::setArray('toast', t('Cambio aplicado.'));
        Redirect::to('/registrados/ajustes');
    }
}
