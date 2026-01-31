<?php
/**
 */
class PublicacionesController extends AppController
{
    public function __call($name, $params)
    {
        $this->ver($name, ...$params);
    }

    #
    public function index($pagina=0)
    {
        $pagina ? Redirect::to("/index/$pagina", 0, 301) : Redirect::to('/', 0, 301);
    }

    #
    public function buscar($frase='')
    {
        if (Input::post('frase')) {
            $frase = urlencode(Input::post('frase'));
            return Redirect::to("/publicaciones/buscar/$frase");
        }
        if (strlen($frase) < 3) {
            Session::setArray('toast', t('Mínimo 3 caracteres.'));
            return Redirect::to('/publicaciones');
        }
        $grupo = (new Grupos)->unoPorHashtag($frase);
        if ($grupo) {
            return Redirect::to('/grupos/ver/' . urlencode($grupo->nombre));
        }
        (new Buscador)->registrar($frase);
        $this->publicaciones = (new Buscador)->publicaciones($frase);
        $this->title = t('Buscando: ') . $frase;
        $this->frase = $frase;
        $this->variables();
        View::select('todas');
    }    
    
    #
    public function compartir($test=false)
    {
        if (empty($_REQUEST['url'])) {
            return Redirect::to('/');
        }
        $this->compartir_desde_fuera = $_REQUEST['url'];

        if ($test) {
            $data = _preview::url($_REQUEST['url']);
            _var::die($data);
        }
    }

    #
    public function variables()
    {
        $this->experiencia = (new Experiencia)->entregada($this->publicaciones);
        $this->encuestas = (new Encuestas)->todas($this->publicaciones);
        $this->encuestas_opciones = (new Encuestas)->opciones($this->encuestas);
        $this->vistas_previas = (new Vistas_previas)->obtenerTodas($this->publicaciones);
        $this->consejo = (new Publicidad)->barajar();
        $this->notificar = (new Acciones)->registros('notificar');
        $this->roles = Config::get('roles.singular'); 
        $this->etiquetas_usuario = (new Etiquetas_usuarios)->todas();
    }

    #
    public function ver($slug)
    {
        $this->pub = (new Publicaciones)->una($slug);
        $this->publicaciones = [$this->pub];
        $this->title = "{$this->pub->titulo} | R+";
        $this->description = $this->pub->contenido_formateado;

        if ( ! empty($this->pub->fotos)) {
            $this->image = '/img/usuarios/' . $this->pub->usuarios_idu . '/l.';

            $this->image .= strstr($this->pub->fotos, ',')
                ? explode(',', $this->pub->fotos)[0]
                : $this->pub->fotos;
        }

        $this->variables();
        View::select('ver');
    }

    #
    public function ver_experiencia($publicaciones_idu)
    {
        $this->quienes = (new Experiencia)->enPublicacion($publicaciones_idu);
        $this->titulo = t('Cautivados');
        $this->filtro_filas = '.cautivados li';
        View::template('ventana');
    }

    #
    public function ver_votantes($publicaciones_idu)
    {
        $this->quienes = (new Encuestas)->votando($publicaciones_idu);
        $this->titulo = t('Votantes');
        $this->filtro_filas = '.votantes li';
        View::template('ventana');
    }
}
