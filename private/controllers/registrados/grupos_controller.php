<?php
#
class GruposController extends RegistradosController
{
    # R+2
    public function editor($grupos_idu='')
    {
        if (Input::post()) {
            $hashtag = (new Grupos)->salvar(Input::post());
            Redirect::to("/grupos/ver/$hashtag");
        }
        $this->grupo = (new Grupos)->uno($grupos_idu);
        $this->confirmar_cierre = true;
        $this->titulo = t('Su grupo');
        View::template('ventana');
    }

    # R+2
    public function editar_elemento_de_mapa($mapa_elementos_idu, $marcador)
    {
        $this->elemento = (new Mapa_elementos)->uno($mapa_elementos_idu);
        $this->marcador = $marcador;
        View::select('formulario');
    }

    # R+2
    public function crear_elemento_de_mapa()
    {
        $this->elementos[0] = (new Mapa_elementos)->salvar(Input::post());
        View::select('elemento_de_mapa');
    }

    # R+2
    public function actualizar_elemento_de_mapa($marcador)
    {
        $this->elementos[0] = (new Mapa_elementos)->salvar(Input::post());
        $this->marcador = $marcador;
        View::select('elemento_de_mapa');
    }

    # R+2
    public function eliminar_elemento_de_mapa($mapa_elementos_idu, $marcador)
    {
        (new Mapa_elementos)->eliminar($mapa_elementos_idu);
        $this->idu = $mapa_elementos_idu;
        $this->marcador = $marcador;
    }

    #
    public function dejar($grupos_idu)
    {
        (new Usuarios_grupos)->dejar($grupos_idu);
        Redirect::to('/grupos');
    }

    #
    public function unirse($grupos_idu)
    {
        (new Usuarios_grupos)->unirse($grupos_idu);
        $grupo = (new Grupos)->uno($grupos_idu);
        Redirect::to('/grupos/ver/' . urlencode($grupo->nombre));
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
    */
}
