<?php
#
class GruposController extends AppController
{
    #
    public function index($pagina=1)
    {
        $this->grupos = (new Grupos)->todos(['order'=>'ultima_pub DESC']);
        $this->u_g = (new Usuarios_grupos)->todos();
        $this->title = t('Grupos') . ' > ' . t('Todos');
    }

    #
    public function buscar($frase='')
    {
        if (Input::post('frase')) {
            $frase = urlencode(Input::post('frase'));
            return Redirect::to("/grupos/buscar/$frase");
        }
		if (strlen($frase) < 3) {
            Session::setArray('toast', t('Mínimo 3 caracteres.'));
            return Redirect::to('/grupos');
        }
        (new Buscador)->registrar($frase);
        $this->grupos = (new Buscador)->grupos($frase);
        $this->u_g = (new Usuarios_grupos)->todos();
        $this->title = t('Buscando: ') . $frase;
        $this->frase = $frase;
        $this->roles = Config::get('roles.singular'); 
        View::select('index');
    }

    #
    public function mapa($grupos_hashtag)
    {
        $this->grupo = (new Grupos)->unoPorHashtag($grupos_hashtag);
        $this->elementos = (new Mapa_elementos)->todos($this->grupo->idu);
        $this->elemento = (new Mapa_elementos)->cols();
        $this->marcador = '';
        View::template('mapa');
    }

    #
    public function variables($publicaciones)
    {
        /*if ( ! Session::get('idu')) {
            $this->consejo = (new Publicidad)->barajar();
        }*/
        $this->encuestas = (new Encuestas)->todas($publicaciones);
        $this->encuestas_opciones = (new Encuestas)->opciones($this->encuestas);
        $this->experiencia = (new Experiencia)->entregada($publicaciones);
        $this->roles = Config::get('roles.singular');
        $this->vistas_previas = (new Vistas_previas)->obtenerTodas($publicaciones);
        $this->notificando = (new Acciones)->registrosPorElementoYAccion('usuarios', 'notificar');
    }

    #
    public function ver($hashtag='', $elemento_tipo='', $elemento='')
    {
        $this->grupo = (new Grupos)->unoPorHashtag($hashtag);
        if ( ! $this->grupo){
            $grupo = (new Grupos)->unoPorNombre($hashtag);
            if ($grupo){
                return Redirect::to("/grupos/ver/$grupo->hashtag");
            }
            return Redirect::to("/publicaciones/buscar/$hashtag");
        }

        $this->title = $this->grupo->nombre . ' | ' . t('Grupo') . ' R+';
        $this->description = "{$this->grupo->eslogan} | {$this->grupo->info}";
        $this->image = "/img/usuarios/{$this->grupo->usuarios_idu}/l.{$this->grupo->fondo_cabecera}";

        $this->elemento = $elemento_tipo;

        $this->quienes = (new Usuarios_grupos)->enGrupo($this->grupo->idu);

        $this->fondo_general_idu = $this->grupo->usuarios_idu;
        $this->fondo_general = $this->grupo->fondo_general;

        $this->publicaciones = (new Publicaciones)->porEtiquetas($this->grupo->hashtag);

        $this->variables($this->publicaciones);
    }

    #
    public function ver_miembros($grupos_idu)
    {
        $this->quienes = (new Usuarios_grupos)->enGrupo($grupos_idu);
        $this->titulo = t('Miembros');
        $this->filtro_filas = '.miembros li';
        View::template('ventana');
    }

    /*
    #
	protected function before_filter()
	{
        if ($action = Input::post('action'))
        {
            unset($_POST['action']);
            if ( method_exists($this, $action) ) $this->$action();
        }
    }

    #
    public function arreglo()
    {
        $grupos = (new Grupos)->todos();
        foreach ($grupos as $gru)
        {
            $sql = 'SELECT * FROM usuarios_grupos WHERE usuarios_idu=? AND grupos_idu=?';
            $o = (new Usuarios_grupos)->first($sql, [$gru->usuarios_idu, $gru->idu]);
            _::e([$sql, $gru, $o]);
            echo '<br>';
            if ($o)
            {
                echo 'El creador ya es miembro de su grupo... ;)';
            }
            else
            {
                $sql = 'INSERT INTO usuarios_grupos SET usuarios_idu=? AND grupos_idu=?';
                (new Usuarios_grupos)->query($sql, [$gru->usuarios_idu, $gru->idu]);

                echo 'Metiendo al creador en su grupo... ;)';
            }
            echo '<hr>';
        }
        die;
    }

    #
    public function actualizar($idu, $a='', $b='')
    {
        if ($_POST) $a = $_POST;
        (new Grupos)->actualizar($idu, $a, $b);
        $url = parse_url($_SERVER['HTTP_REFERER']);
		exit( Redirect::to($url['path']) );
    }

    #
    public function eliminar($idu)
    {
        (new Grupos)->eliminar($idu);
        exit( Redirect::to('/grupos') );
    }

    #
    public function crear_carpeta($grupos_idu)
    {
        (new Grupos_carpetas)->crear($grupos_idu, $_POST);
        $grupo = (new Grupos)->uno($grupos_idu);
        exit( Redirect::to( '/grupos/ver/' . urlencode($grupo->nombre) ) );
    }

    #
    public function carpetas($grupo_nombre, $carpetas_idu='')
    {
        $this->carpeta = (new Carpetas)->unaDeGrupo($grupo_nombre, $carpetas_idu);
        $this->publicaciones = (new Publicaciones)->carpetaDeGrupo($grupo_nombre, $carpetas_idu);
        $this->grupo_nombre = urldecode($grupo_nombre);
    }

    #
    public function crear()
    {
        $nombre = (new Grupos)->crear($_POST);
        exit( Redirect::to( '/grupos/ver/' . urlencode($nombre) ) );
    }
    */
}
