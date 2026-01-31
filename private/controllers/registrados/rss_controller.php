<?php
/**
 */
class RssController extends RegistradosController
{
	#
	protected function before_filter()
	{
        if ($this->usuario->rol < 3) {
            Session::setArray('toast', t('Esta sección es para el rol portaestandarte o superior.'));
            Redirect::to('/registrados/tienda');
            return false;
        }
    }

    #
    public function index()
    {
        if (Input::post('action') == 'incluir') {
            (new Rss_sitios)->incluirSitio($_POST);
        }
        else if (Input::post('action') == 'no_ver') {
            (new Rss_suscripciones)->suscribirLista($_POST['sitios']);
        }
		$this->idiomas = Config::get('combos.idiomas');
        $this->sitios = (new Rss_sitios)->obtenerSitios();
        $this->suscripciones = (new Rss_suscripciones)->obtenerSuscripciones();
        $this->entradas = (new Rss_entradas)->obtenerEntradas();
    }

    #
    public function publicar($idu)
    {
        (new Rss_entradas)->publicarEntrada($idu);
        View::select('');
    }

    #
    public function quitar($rss_sitios_idu)
    {
        (new Rss_sitios)->quitarSitio($rss_sitios_idu);
        Redirect::to('/registrados/rss');
    }

    #
    public function parchear()
    {
        (new Rss_entradas)->parchear();
        Redirect::to('/registrados/rss');
    }
}
