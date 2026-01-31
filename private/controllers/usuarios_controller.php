<?php
/**
 */
class UsuariosController extends AppController
{
    #
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
    public function index($pagina=1)
    {
        /*if ( ! $pagina) {
            return Redirect::to("/usuarios/index/1");
        }*/
        $this->title = 'Usuarios de R+';
        $this->pagina = $pagina;
        $this->ultima_pagina = (new Usuarios)->ultimaPagina();
        $this->usuarios = (new Usuarios)->ultimosPublicando($pagina);
        $this->roles = Config::get('roles.singular'); 
        $this->notificando = (new Acciones)->registrosPorElementoYAccion('usuarios', 'notificar');
        $this->bloqueados = (new Acciones)->registros('bloqueado');
        if (Input::isAjax()) {
            View::select('listado');
        }
    }

    #
    public function apodos($palabra)
    {
        $this->usuarios = (new Usuarios)->ofrecerApodos($palabra);
    }

    #
    public function baja_del_boletin($token)
    {
        (new Configuracion)->asignarPorToken($token, 'no_al_boletin', 1);
    }

    #
    /*public function borrarse()
    { 
        (new Usuarios)->borrarse($_POST);
        Redirect::to('/');
    }*/

    #
    public function buscar($frase='')
    {
        if (Input::post('frase')) {
            $frase = urlencode(Input::post('frase'));
            return Redirect::to("/usuarios/buscar/$frase");
        }
		if (strlen($frase) < 3) {
            Session::setArray('toast', t('Mínimo 3 caracteres.'));
            return Redirect::to('/usuarios');
        }

        $frase = urldecode($frase);
        $frase = strip_tags($frase);
        $frase = preg_replace('/[^\d\w\.,; -]/u', '', $frase);

        (new Buscador)->registrar($frase);
        $this->usuarios = (new Buscador)->usuarios($frase);
        $this->title = t('Buscando: ') . $frase;
        $this->frase = $frase;
        $this->roles = Config::get('roles.singular'); 
        $this->notificando = (new Acciones)->registrosPorElementoYAccion('usuarios', 'notificar');

        View::select('index');
    }
    
    #
    public function cambiar_idioma($iso='')
    {
        (new Usuarios)->establecerIdioma($iso);
        Redirect::to('/');
    }

    #
    public function conectado()
    {
        (new Usuarios)->marcarAcceso();
        View::select(null, null);
    }

    #
    public function conectados()
    {        
        View::template(null);
    }

    #
    public function confirmar($email, $clave)
    {
        $usuario = (new Usuarios)->confirmar($email, $clave);
        Redirect::to('/usuarios/perfil/' . urlencode($usuario->apodo));
    }

    # f0
    public function formularios()
    {
        Redirect::to('/usuarios/identificarse');
    }

    # f1
    public function identificarse()
    { 
        if (Input::post('identificarse')) {
            (new Usuarios)->identificarse($_POST);
            Session::get('idu')
                ? Redirect::to('/')
                : Redirect::to('/usuarios/identificarse');
        }
        #View::template('v3_5_nosferatu');
    }

    # f2
    public function registrarse()
    { 
        if (Input::post('registrarse')) {
            (new Usuarios)->registrarse($_POST);
            Redirect::to('/usuarios/identificarse');
        }
    }

    # f3
    public function recordar_clave()
    { 
        if (Input::post('recordar_clave')) {
            (new Usuarios)->recordar_clave($_POST);
            Redirect::to('/usuarios/identificarse');
        }
    }

    #
    public function identificarse_con_google()
    {
        (new Usuarios)->identificarseConGoogle();
        return Redirect::to('/'); 
    }

    #
    public function registrarse_con_google()
    {
        (new Usuarios)->registrarseConGoogle();
        return Redirect::to('/'); 
    }

    #
    public function guardar_perfil()
    { 
        if ( ! Session::get('idu')) {
            Session::setArray('toast', t('Identifícate primero.'));
            Redirect::to('/');
        }
        $ok = (new Usuarios)->guardarPerfil(Input::post());

        $url = parse_url($_SERVER['HTTP_REFERER'])['path'];

        if ($ok && Input::post('apodo_anterior') <> Input::post('apodo') && strstr($url, 'usuarios/perfil/' . urlencode(Input::post('apodo_anterior')))) {
            return Redirect::to('/usuarios/perfil/' . urlencode(Input::post('apodo')));
        }

		return Redirect::to($url);
    }

    #
    public function perfil($apodo='', $tipo='', $parametro_uno='', $parametro_dos='')
    {
        if ( ! $apodo) {
            return Redirect::to('/usuarios/formularios');
        }
        elseif ( ! $tipo) {
            $this->todas($apodo);
        }       
        elseif ($tipo == 'publicacion') {
            $pub = (new Publicaciones)->una($parametro_uno);
            return Redirect::to("/publicaciones/$pub->slug", 0, 301);
        } 
        elseif ($tipo == 'etiqueta') {
            $this->etiqueta($apodo, $parametro_uno);
        }
        elseif ($tipo == 'fecha') {
            $this->fecha($apodo, $parametro_uno, $parametro_dos);
        }
    }

    #
    public function todas($apodo)
    {
        $this->usu = (new Usuarios)->uno($apodo);
        if ($this->usuario->idu==$this->usu->idu && ! Session::get('idu')) {
            Redirect::to('/usuarios/formularios');
        }
        $this->publicaciones = (new Publicaciones)->todasPorUsuario($this->usu->idu) ?? [];
        $this->title = $this->usu->apodo . t(': Últimas noticias y novedades.');
        $this->variables($this->publicaciones);
    }

    #
    /*public function una($apodo, $publicaciones_idu)
    {
        $this->usu = (new Usuarios)->uno($apodo);
        $this->publicaciones[] = (new Publicaciones)->una($publicaciones_idu);
        if (empty($this->publicaciones[0]->idu)) {
            Session::setArray('toast', t('La publicación ya no está disponible.'));
            return Redirect::to('/');
        }
        $pub = $this->publicaciones[0];
        $this->title = $pub->titulo ?? '';
        if ($this->title) {
            $this->title .= ' - ' . $pub->apodo;
        }
        $this->description = $pub->contenido_formateado);

        if ( ! empty($pub->fotos)) {
            $this->image = 'https://' . $_SERVER['HTTP_HOST'] . '/img/usuarios/' . $pub->usuarios_idu . '/l.';

            $this->image .= strstr($pub->fotos, ',')
                ? explode(',', $pub->fotos)[0]
                : $pub->fotos;
        }

        # /img/vistas_previas/2bdf6535a41/l.QueSonLasLES2022.jpg
        $vistas_previas = (new Vistas_previas)->obtenerTodas($this->publicaciones);
        if ( ! empty($vistas_previas[$pub->idu][0]->image)) {
            $vp_idu = $vistas_previas[$pub->idu][0]->idu;
            $vp_image = $vistas_previas[$pub->idu][0]->image;
            $this->image = "/img/vistas_previas/$vp_idu/l.$vp_image";
            #_var::die($this->image);
        }

        $this->publicaciones_idu = $publicaciones_idu;
        $this->variables($this->publicaciones);
    }*/

    #
    public function etiqueta($apodo, $etiqueta)
    {
        $this->usu = (new Usuarios)->uno($apodo);
        $this->publicaciones = (new Publicaciones)->porEtiqueta($this->usu, $etiqueta) ?? [];
        $this->variables($this->publicaciones);
    }

    #
    public function fecha($apodo, $anyo, $mes='')
    {
        $this->usu = (new Usuarios)->uno($apodo);
        $this->publicaciones = (new Publicaciones)->porFecha($this->usu, $anyo, $mes) ?? [];
        $this->variables($this->publicaciones);
    }

    #
    public function resetear($email, $clave='')
    {
        (new Usuarios)->resetear($email, $clave);
        Redirect::to('/usuarios/formularios');
    }

    #
    public function salir()
    {
        (new Usuarios)->salir();
        Redirect::to('/');
    }

    #
    public function variables($publicaciones)
    {
        if ( ! Session::get('idu')) {
            $this->consejo = (new Publicidad)->barajar();
        }
        $this->bloqueados = (new Acciones)->registros('bloqueado');
        $this->encuestas = (new Encuestas)->todas($publicaciones);
        $this->encuestas_opciones = (new Encuestas)->opciones($this->encuestas);
        $this->experiencia = (new Experiencia)->entregada($publicaciones);
        
        $this->notificando = (new Acciones)->registrosPorElementoYAccion('usuarios', 'notificar');
        
        $this->notificar = (new Acciones)->registros('notificar');

        $this->roles = Config::get('roles.singular');
        $this->vistas_previas = (new Vistas_previas)->obtenerTodas($publicaciones);
    }

    #
    public function ver_conectados()
    {
        $this->quienes = (new Usuarios)->conectados();
        _url::enviarAlCanal('rp_conectados', '/usuarios/conectados');
        $this->titulo = t('Conectados');
        $this->filtro_filas = '.ver_conectados li';
        View::template('ventana');
    }

    #
    /*public function index($pagina=1)
    {
        $this->title = 'Usuarios de R+';
        $this->pagina = $pagina;
        $this->usuarios = (new Usuarios)->ultimosPublicando($pagina);
        $this->variables();
        if (Input::isAjax()) {
            View::select('listado');
        }
    }

    #
    public function buscar($frase='')
    {
        if (Input::post('frase')) {
            $frase = urlencode(Input::post('frase'));
            return Redirect::to("/usuarios/buscar/$frase");
        }
		if (strlen($frase) < 3) {
            Session::setArray('toast', t('Mínimo 3 caracteres.'));
            return Redirect::to('/usuarios');
        }
        (new Buscador)->registrar($frase);
        $this->usuarios = (new Buscador)->usuarios($frase);
        $this->title = t('Buscando: ') . $frase;
        $this->frase = $frase;
        $this->variables();
        View::select('index');
    }

    #
    private function variables()
    {
        $this->claves = (new Configuracion)->todas();
        $this->roles = (new Usuarios)->roles();
        $this->usuario = (new Usuarios)->uno();
    }

    #
    public function perfil($apodo='', $no_se_usa='', $publicacion_idu='')
    {
        $this->usu = (new Usuarios)->uno($apodo);
        if ($this->usu->rol < 1) {
            return Redirect::to('/registrados/usuarios/entrar');
        }
        $this->elemento = $no_se_usa;
        if ( ! $this->usu) {
            Session::setArray('toast', t('El usuario ya no está disponible.'));
            return Redirect::to('/publicaciones');
        }
        $this->claves = (new Configuracion)->todas();
        $this->experiencia = (new Experiencia)->entregada();

        if ($publicacion_idu) {
            $this->pub = (new Publicaciones)->una($publicacion_idu);
            if ( ! $this->pub) {
                Session::setArray('toast', t('La publicación ya no está disponible.'));
                Redirect::to();
            }
            $this->publicaciones[$this->pub->idu] = $this->pub;
            $this->title = $this->pub->titulo;
        }
        else {
            $this->publicaciones = (new Publicaciones)->todasPorUsuario($this->usu->idu);
            $this->title = t('Perfil de: ') . $apodo;
        }
        $this->encuestas = (new Encuestas)->todas($this->publicaciones);
        $this->encuestas_opciones = (new Encuestas)->opciones($this->encuestas);
        $this->vistas_previas2 = (new Vistas_previas)->obtenerTodas($this->publicaciones);
        $this->apuntados = (new Eventos)->apuntados($this->publicaciones);
        $this->reservas = (new Eventos)->apuntadosEnReserva($this->publicaciones);

        $this->roles = (new Usuarios)->roles();
        $this->fondo_general_idu = $this->usu->idu;
        $this->fondo_general = $this->usu->fondo_general;

        $this->usuario = (new Usuarios)->uno();
    }

    #
    public function insertar_apodo($idu)
    {
        $this->usu = (new Usuarios)->uno($idu);
    }

    #
    public function ventana()
    {
        $this->to = Input::post('to');
        $this->load = Input::post('load');
        $this->usuarios = (new Usuarios)->todos();
    }*/
}
