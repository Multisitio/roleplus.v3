<?php
/**
 */
class YoutubeController extends RegistradosController
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
            (new Rolflix_sitios)->incluirSitio($_POST);
        }
        else if (Input::post('action') == 'no_ver') {
            (new Rolflix_suscripciones)->suscribirLista($_POST['sitios']);
        }
        $this->sitios = (new Rolflix_sitios)->obtenerSitios();
        $this->suscripciones = (new Rolflix_suscripciones)->obtenerSuscripciones();
        $this->entradas = (new Rolflix_entradas)->obtenerEntradas();
        #_var::die($this->entradas);
    }

    #
    public function publicar($idu)
    {
        (new Rolflix_entradas)->publicarEntrada($idu);
        View::select('');
    }

    #
    public function quitar($rss_sitios_idu)
    {
        (new Rolflix_sitios)->quitarSitio($rss_sitios_idu);
        Redirect::to('/registrados/youtube');
    }

    #
    /*public function parchear()
    {
        (new Rolflix_entradas)->parchear();
        Redirect::to('/registrados/youtube');
    }*/
}
