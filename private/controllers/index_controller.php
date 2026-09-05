<?php
/**
 */
class IndexController extends AppController
{
    protected function before_filter()
    {
        if ($this->action_name == 'nchan') {
            $this->limit_params = false;
        }
    }

    #
    public function index($pagina=1)
    {
        $this->publicaciones = (new Publicaciones)->todas($pagina);
        $this->variables();
        View::setPath('publicaciones');
        View::select('todas');
        Session::set('ultimas_publicaciones', date('Y-m-d H:i:s'));
    }

    # Desde el manifest.json se llega a esta acción al compartir con la app de R+
    # El método compartirDesdeFuera devuelve en una cadena el título y la URL
    # La variable compartir_desde_fuera en la plantilla default abre el editor
    # La acción que abre el editor carga contenido en contenido_desde_fuera 
    /*public function compartir($array)
    {
        $this->compartir_desde_fuera = (new Publicaciones)->compartirDesdeFuera($array);
        $this->index();
        View::setPath('publicaciones');
        View::select('todas');
    }*/

    #
    public function preview()
    {
        $this->url = base64_decode($_GET['u']);
        
        /*$this->tags = _link::preview(base64_decode($_GET['u']));
        if ( ! $this->tags) {
            View::select('', null);
        }*/
    }

    #
    public function toast()
    {
        Session::setArray('toast', Input::post('toast'));
        View::select('');
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
    public function joanhey()
    {
        phpinfo();
        View::select(null, null);
    }

    #
    public function pruebas_locas()
    {
        $r = (new Publicaciones)->nuevas();
        _var::die($r);
    }

    #
    public function nchan()
    {
        $this->limit_params = false;
        echo 0;
        View::select('', null);
    }

    public function AAFe46f7DHU1gvSvB9oXO3GnNrpgoYMbty0($bot)
    {
        $this->bot = $bot;
        View::template(null);
    }

    public function AAGVdHqiqF5gHTc2mL9hR3do0Cv7z01xcjY($bot)
    {
        $this->bot = $bot;
        View::template(null);
    }

    public function heic_to_jpg() {
        _file::heicToJpg();
        View::template(null);
    }

    public function prueba_google() {
    }

    public function test_font_download()
    {
        View::select(null, null);
        $fuente = Input::get('fuente') ?: 'Creepster';
        echo "Intentando descargar: $fuente\n";
        $res = Plantillas::descargarFuente($fuente);
        echo "Resultado: " . ($res ? "OK" : "FALLO") . "\n";
        print_r(Session::get('toast'));
    }

    public function test_manual()
    {
        View::select(null, null);
        $manuales_idu = '95eabe075659';
        $manual = (new Manuales)->uno($manuales_idu);
        if (!$manual->idu) {
            echo "Manual no encontrado.\n";
            return;
        }
        echo "Manual: " . $manual->nombre . "\n";
        echo "Plantilla: " . $manual->plantilla . "\n";
        $plantilla = (new Plantillas)->obtenerOCrearPorNombre($manual->plantilla, $manual->usuarios_idu, $manual->plantilla);
        echo "Plantilla IDU: " . $plantilla->idu . "\n";
        $ajustes = $plantilla->getSettings();
        echo "Ajustes Dropcap:\n";
        print_r($ajustes->tipografia['dropcap'] ?? null);
        echo "\nDropcap family: " . $ajustes->dropcap_family . "\n";
        echo "Dropcap size: " . $ajustes->dropcap_size . "\n";
        echo "CSS Fuentes:\n" . $ajustes->css_fuentes . "\n";
        echo "CSS URL: " . $plantilla->getCssUrl() . "\n";
    }
}
