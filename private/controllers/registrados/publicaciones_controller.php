<?php
/**
 */
class PublicacionesController extends RegistradosController
{
    #
    public function apuntarse($publicaciones_idu)
    {
        $this->pub = (new Eventos)->apuntarse($publicaciones_idu);
        $this->apuntados = (new Eventos)->apuntados([$this->pub->idu => $this->pub]);
        $this->reservas = (new Eventos)->apuntadosEnReserva([$this->pub->idu => $this->pub]);
        View::select('apuntados');
    }

    #
    public function crear()
    {
        $pub = (new Publicaciones)->crear($_POST);
		Redirect::to("/publicaciones/$pub->slug");
    }

    #
    public function desapuntarse($publicaciones_idu)
    {
        $this->pub = (new Eventos)->desapuntarse($publicaciones_idu);
        $publicaciones = [$this->pub->idu => $this->pub];
        $this->apuntados = (new Eventos)->apuntados($publicaciones);
        $this->reservas = (new Eventos)->apuntadosEnReserva($publicaciones);
        View::select('apuntados');
    }

    #
    public function eliminar($idu)
    {
        (new Publicaciones)->eliminar($idu);
        View::select('');
    }

    #
    public function eliminar_enlace($idu)
    {
        (new Publicaciones)->eliminarEnlace($idu);
        View::select(null);
    }

    #
    public function experiencia($tipo, $publicaciones_idu)
    {
        $cuanto = ($tipo == 'me_encanta') ? 5 : 1;
        (new Experiencia)->publicaciones($publicaciones_idu, $cuanto);
        $this->pub = (new Publicaciones)->una($publicaciones_idu);
        $experiencia = (new Experiencia)->entregada([$this->pub]);
        $this->px = empty($experiencia['publicaciones'][$this->pub->idu]) ? 0 : $experiencia['publicaciones'][$this->pub->idu];
    }

    #
    public function formulario($publicaciones_idu='')
    {
        $this->publicacion = (new Publicaciones)->una($publicaciones_idu);
        $publicaciones = [$this->publicacion->idu => $this->publicacion];
        $this->encuestas = (new Encuestas)->todas($publicaciones);
        $this->vistas_previas = (new Vistas_previas)->obtenerTodas($publicaciones);

        $this->contenido_desde_fuera = empty($_POST['contenido']) ? '' : $_POST['contenido'];

        $this->grupos = (new Grupos)->todosLosHashtags();

        View::template('ventana');
    }

    #
    public function desde_rss($rss_idu)
    {
        $entrada = (new Rss_entradas)->prepararEntrada($rss_idu);
        $this->publicacion = (object)$entrada;
        $this->titulo = $this->publicacion->titulo
            ?? t('Publicación de una entrada RSS');
        $this->confirmar_cierre = true;
        #$this->grupos = (new Grupos)->todosLosHashtags();
        View::select('formulario', 'ventana');
    }

    #
    public function desde_youtube($youtube_idu)
    {
        $entrada = (new Rolflix_entradas)->prepararEntrada($youtube_idu);
        $this->publicacion = (object)$entrada;
        $this->titulo = $this->publicacion->titulo
            ?? t('Publicación de una entrada RSS');
        $this->confirmar_cierre = true;
        #$this->grupos = (new Grupos)->todosLosHashtags();
        View::select('formulario', 'ventana');
    }

    #
    public function notificar($publicaciones_idu, $alternar=1)
    {
        if ($alternar) {
            (new Acciones)->alternar($publicaciones_idu, 'publicaciones', 'notificar');
        }
        $this->notificar = (new Acciones)->registros('notificar');
        $this->idu = h($publicaciones_idu);
    }

    #
    public function nuevas()
    {
        $this->publicaciones_nuevas = (new Publicaciones)->nuevas();
    }

    #
    public function perfiles()
    {
        $this->publicaciones = (new Publicaciones)->perfilesSiguiendo();
        $this->variables();
        View::setPath('publicaciones');
        View::select('todas');
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
    public function votar($publicaciones_idu, $opciones_idu='')
    {
        (new Encuestas)->votar($publicaciones_idu, $opciones_idu);
        $this->pub = (new Publicaciones)->una($publicaciones_idu);

        $publicaciones[] = (object)['idu'=>$publicaciones_idu];
        $this->encuestas = (new Encuestas)->todas($publicaciones);
        $this->encuestas_opciones = (new Encuestas)->opciones($this->encuestas);
        $this->total = 0;
        foreach ($this->encuestas[$publicaciones_idu] as $una) {
            $this->total += $una->votos;
        }
    }
}
