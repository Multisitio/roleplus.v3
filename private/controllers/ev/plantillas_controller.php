<?php
/**
 */
class PlantillasController extends EvController
{
    /**
     * Filtro antes de cada acción.
     */
	protected function before_filter()
	{
        if ($this->usuario->rol < 5) {
            Session::setArray('toast', t('Adquiera el rol Chamán.'));
            return Redirect::to('/registrados/tienda');
        }

        if ($action = Input::post('action')) {
            unset($_POST['action']);
            if (method_exists($this, $action)) {
                $this->$action();
                return false;
            }
        }
    }

    /**
     * Listado de plantillas.
     */
    public function index($idu='')
    {
        $this->plantillas = (new Plantillas)->todas($idu);
    }

    /**
     * Formulario de creación/edición.
     */
    public function formulario($idu='')
    {
        $this->plantilla = (new Plantillas)->una($idu);
        $this->reglas = (new Plantillas_reglas)->todas($idu);
    }

    /**
     * Formulario para editar la información básica de la plantilla (vía AJAX).
     */
    public function formulario_plantilla($idu='')
    {
        $this->plantilla = (new Plantillas)->una($idu);
    }

    /**
     * Acción para actualizar.
     */
    public function actualizar()
    {
        $idu = (new Plantillas)->actualizar($_POST);
        Redirect::to('/ev/plantillas/formulario/' . $idu);
    }

    /**
     * Acción para crear.
     */
    public function crear()
    {
        $idu = (new Plantillas)->crear($_POST);
        Redirect::to('/ev/plantillas/formulario/' . $idu);
    }

    /**
     * Acción para eliminar.
     */
    public function eliminar()
    {
        (new Plantillas)->eliminar($_POST['idu']);
        Redirect::to('/ev/plantillas');
    }

    /**
     * Acciones para manejar las reglas de la plantilla:
     */

    public function regla_crear()
    {
        $this->_procesar_fuentes_google($_POST);
        (new Plantillas_reglas)->crear($_POST);
        Redirect::to(parse_url($_SERVER['HTTP_REFERER'])['path']);
    }

    public function regla_actualizar()
    {
        $this->_procesar_fuentes_google($_POST);
        (new Plantillas_reglas)->actualizar($_POST);
        Redirect::to(parse_url($_SERVER['HTTP_REFERER'])['path']);
    }

    public function regla_eliminar()
    {
        (new Plantillas_reglas)->eliminar($_POST['idu']);
        Redirect::to(parse_url($_SERVER['HTTP_REFERER'])['path']);
    }

    public function selector_eliminar()
    {
        $selector = $_POST['selector'] ?? '';
        $plantilla_idu = $_POST['plantilla_idu'] ?? '';
        if ($selector && $plantilla_idu) {
            (new Plantillas_reglas)->query('DELETE FROM plantillas_reglas WHERE plantillas_idu=? AND selector=?', [$plantilla_idu, $selector]);
        }
        Redirect::to(parse_url($_SERVER['HTTP_REFERER'])['path']);
    }

    public function ordenar_reglas()
    {
        header('Content-Type: application/json; charset=utf-8');
        View::select(null, null);

        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (!empty($data['orden']) && is_array($data['orden'])) {
            $modelo = new Plantillas_reglas();
            foreach ($data['orden'] as $index => $selector) {
                $modelo->query('UPDATE plantillas_reglas SET peso=? WHERE selector=?', [$index, $selector]);
            }
            // Marcar para compilación al reordenar
            $primera = $modelo->first('SELECT plantillas_idu FROM plantillas_reglas WHERE selector=?', [$data['orden'][0]]);
            if ($primera) {
                (new Plantillas)->query('UPDATE plantillas SET requiere_compilacion=1 WHERE idu=?', [$primera->plantillas_idu]);
            }
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Datos inválidos']);
        }
    }

    /**
     * Compilador de plantilla a CSS real.
     */
    public function compilar_plantilla($idu)
    {
        View::select(null, null);
        $plantilla = (new Plantillas)->una($idu);
        if (!$plantilla->idu) {
            Session::setArray('toast', t('Plantilla no encontrada.'));
            return Redirect::to('/ev/plantillas');
        }

        // Obtener reglas ordenadas por selector para agrupar
        $reglas = (new Plantillas_reglas)->all('SELECT * FROM plantillas_reglas WHERE plantillas_idu=? ORDER BY selector ASC, peso ASC', [$idu]);
        
        $css = "/* Compilado: " . date('Y-m-d H:i:s') . " */\n";
        $agrupado = [];
        $fuentes_usadas = [];
        foreach ($reglas as $r) {
            $agrupado[$r->selector][] = "    {$r->propiedad}: {$r->valor};";
            if ($r->propiedad === 'font-family') {
                $nombre_fuente = trim($r->valor, "'\" ");
                $fuentes_usadas[$nombre_fuente] = true;
            }
        }

        // Agregar @font-face
        foreach (array_keys($fuentes_usadas) as $fuente) {
            $nombre_archivo = strtolower(str_replace(' ', '_', $fuente)) . '.woff2';
            $css .= "@font-face {\n    font-family: '{$fuente}';\n    src: url('../fonts/{$nombre_archivo}');\n}\n\n";
        }

        foreach ($agrupado as $selector => $props) {
            $css .= "{$selector} {\n" . implode("\n", $props) . "\n}\n\n";
        }

        $ruta = "css/plantillas/{$idu}.css";
        $dir = dirname($ruta);
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        if (file_put_contents($ruta, $css) !== false) {
            (new Plantillas)->query('UPDATE plantillas SET requiere_compilacion=0 WHERE idu=?', [$idu]);
            Session::setArray('toast', t('Web actualizada con éxito.'));
        } else {
            Session::setArray('toast', t('Error al escribir el archivo CSS.'));
        }

        Redirect::to('/ev/plantillas/formulario/' . $idu);
    }

    /**
     * Formulario para editar una regla (vía AJAX).
     */
    public function formulario_regla($plantillas_idu, $reglas_idu='')
    {
        $this->plantilla = (new Plantillas)->una($plantillas_idu);
        $this->plantillas_idu = $this->plantilla->idu;
        
        $this->reglas_idu = $reglas_idu;
        $this->reglas = [];
        if ($reglas_idu) {
            $regla = (new Plantillas_reglas)->una($reglas_idu);
            if ($regla->idu) {
                // Cargamos todas las reglas que comparten el mismo selector
                $this->reglas = (new Plantillas_reglas)->all('SELECT * FROM plantillas_reglas WHERE plantillas_idu=? AND LOWER(TRIM(selector)) = LOWER(TRIM(?)) ORDER BY peso ASC, id ASC', [$plantillas_idu, $regla->selector]);
            }
        }

        // Si no hay reglas (nueva regla), inicializamos una vacía
        if (empty($this->reglas)) {
            $this->reglas = [(new Plantillas_reglas)->una()];
        }

        $this->selectores = (new Plantillas_selectores)->todos();
        $this->propiedades = (new Plantillas_propiedades)->todos();
        
        // Para cada regla, cargamos sus valores posibles si tiene propiedad seleccionada
        $this->valores_reglas = [];
        foreach ($this->reglas as $reg) {
            $ridu = (string)$reg->idu;
            $this->valores_reglas[$ridu] = [];
            if ($reg->propiedad) {
                $prop = (new Plantillas_propiedades)->first('SELECT idu FROM plantillas_propiedades WHERE propiedad=?', [$reg->propiedad]);
                if ($prop) {
                    $this->valores_reglas[$ridu] = (new Plantillas_valores)->por_propiedad($prop->idu);
                }
            }
        }
    }

    /**
     * Formulario para importar CSS (vía AJAX).
     */
    public function importar_css($plantillas_idu)
    {
        $this->plantilla = (new Plantillas)->una($plantillas_idu);
        $this->plantillas_idu = $this->plantilla->idu;
    }

    /**
     * Procesa el CSS pegado y crea las reglas.
     */
    public function procesar_importacion()
    {
        $plantillas_idu = Input::post('plantillas_idu');
        $css = Input::post('css');
        if (!$plantillas_idu || !$css) {
            Redirect::to('/ev/plantillas');
            return;
        }

        // 1. Limpiar comentarios
        $css = preg_replace('!/\*.*?\*/!s', '', $css);
        
        // 2. Buscar bloques selector { ... }
        preg_match_all('/([^{]+)\{([^}]+)\}/s', $css, $matches, PREG_SET_ORDER);
        
        $modelo = new Plantillas_reglas();
        
        foreach ($matches as $m) {
            $selector = trim($m[1]);
            $cuerpo = trim($m[2]);
            
            // 3. Buscar propiedades y valores de forma robusta
            // Protegemos ; y : dentro de paréntesis y comillas manualmente
            $cuerpo_protegido = '';
            $in_parenthesis = 0;
            $in_string = false;
            $quote_char = '';
            $len = strlen($cuerpo);
            
            for ($i = 0; $i < $len; $i++) {
                $char = $cuerpo[$i];
                if ($in_string) {
                    if ($char == $quote_char && ($i == 0 || $cuerpo[$i-1] != '\\')) {
                        $in_string = false;
                    }
                    if ($char == ';') $char = "\x01";
                    if ($char == ':') $char = "\x02";
                } else {
                    if ($char == '"' || $char == "'") {
                        $in_string = true;
                        $quote_char = $char;
                    } elseif ($char == '(') {
                        $in_parenthesis++;
                    } elseif ($char == ')') {
                        $in_parenthesis--;
                        if ($in_parenthesis < 0) $in_parenthesis = 0;
                    } elseif ($in_parenthesis > 0) {
                        if ($char == ';') $char = "\x01";
                        if ($char == ':') $char = "\x02";
                    }
                }
                $cuerpo_protegido .= $char;
            }

            $prop_lines = explode(';', $cuerpo_protegido);
            
            foreach ($prop_lines as $line) {
                $line = trim($line);
                if (!$line) continue;

                // Restauramos los dos puntos para separar propiedad de valor
                $line = str_replace("\x02", ':', $line);
                
                if (strpos($line, ':') !== false) {
                    list($p, $v) = explode(':', $line, 2);
                    $p = trim($p);
                    // Restauramos los puntos y coma en el valor
                    $v = trim(str_replace("\x01", ';', $v));
                    
                    if ($p && $v) {
                        $modelo->guardar_una($plantillas_idu, $selector, $p, $v);
                    }
                }
            }
        }

        Redirect::to('/ev/plantillas/formulario/' . $plantillas_idu);
    }

    /**
     * Devuelve todas las propiedades (JSON).
     */
    public function obtener_propiedades()
    {
        header('Content-Type: application/json; charset=utf-8');
        View::select(null, null);
        echo json_encode((new Plantillas_propiedades)->todos());
    }

    /**
     * Devuelve los valores predefinidos para una propiedad (JSON).
     */
    public function obtener_valores($propiedades_idu)
    {
        header('Content-Type: application/json; charset=utf-8');
        View::select(null, null);
        echo json_encode((new Plantillas_valores)->por_propiedad($propiedades_idu));
    }

    /**
     * Ordenar propiedades dentro de un selector.
     */
    public function ordenar_propiedades()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['orden'])) {
            die(json_encode(['ok' => false]));
        }

        $modelo = new Plantillas_reglas;
        foreach ($data['orden'] as $index => $idu) {
            $modelo->query('UPDATE plantillas_reglas SET peso=? WHERE idu=?', [$index, $idu]);
        }

        die(json_encode(['ok' => true]));
    }

    private function _procesar_fuentes_google($post)
    {
        $propiedades = (array)($post['propiedad'] ?? []);
        $valores     = (array)($post['valor'] ?? []);
        
        foreach ($propiedades as $index => $prop) {
            if ($prop === 'font-family') {
                $valor = trim($valores[$index] ?? '');
                if ($valor && !in_array(strtolower($valor), ['inherit', 'initial', 'none'])) {
                    // Remover comillas del valor
                    $valor = trim($valor, "'\"");
                    Plantillas::descargarFuente($valor);
                }
            }
        }
    }
}
