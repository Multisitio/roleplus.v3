<?php
/**
 */
class RoleflixController extends RegistradosController
{
    #
	protected function before_filter()
	{
        if ($this->usuario->rol < 4) {
            Session::setArray('toast', t('Adquiera el rol Azotamentes.'));
            return Redirect::to('/registrados/tienda');
        }

        Input::isAjax() ? View::template('ajax') : View::template('roleflix');

        $this->etiquetas = (new Rolflix_entradas)->etiquetas();
        if ($this->action_name == 'buscar' or array_key_exists($this->action_name, $this->etiquetas)) {
            $this->parameters[0] = $this->action_name;
            $this->action_name = 'index';
            View::select('index');
        }
    }

    #
    public function index($etiqueta='')
    {
        if (Input::post('action') == 'incluir') {
            (new Rolflix_sitios)->incluirSitio($_POST);
        }
        else if (Input::post('action') == 'suscribirse') {
            (new Rolflix_suscripciones)->suscribirLista($_POST['sitios']);
        }

        $this->sitios = (new Rolflix_sitios)->obtenerSitios();
        $this->videos = (new Rolflix_entradas)->obtenerVideos($etiqueta);
        $this->mi_lista = (new Rolflix_entradas)->obtenerMiLista();
        $this->etiqueta = $etiqueta;
        $this->usuario = (new Usuarios)->uno();
    }

    #
    public function arreglo()
    {
        (new Rolflix_entradas)->arreglo();
        die;
    }

    #
    public function configurar()
    {
        $this->sitios = (new Rolflix_sitios)->obtenerSitios();
        $this->suscripciones = (new Rolflix_suscripciones)->obtenerSuscripciones();
    }

    #
    public function etiquetado()
    {
        $this->etiquetas = (new Rolflix_entradas)->etiquetarVideo($_POST);
    }

    #
    public function etiquetar($idu)
    {
        $this->video = (new Rolflix_entradas)->obtenerVideo($idu);
    }

    #
    public function alternar($idu)
    {
        $this->alternado = (new Rolflix_entradas)->anternarDeMiLista($idu);
    }

    #
    public function mi_lista()
    {
        $this->index();
        $this->videos = (new Rolflix_entradas)->obtenerMiLista();
        $this->etiqueta = 'mi_lista';
        View::select('index');
    }

    #
    public function quitar($roleflix_sitios_idu)
    {
        (new Rolflix_sitios)->quitarSitio($roleflix_sitios_idu);
        Redirect::to('/registrados/roleflix');
    }
}
