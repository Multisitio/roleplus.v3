<?php
/**
 */
class ManualesController extends EvController
{
    #
	protected function before_filter()
	{
        if ($this->action_name === 'check_db') return;
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

    #
    public function index($manuales_idu='')
    {
        $this->manuales = (new Manuales)->todos($manuales_idu);
    }

    #
    public function formulario($manuales_idu='')
    {
        $this->manual = (new Manuales)->uno($manuales_idu);
        if ($manuales_idu !== '' && !$this->manual->idu) {
            Session::setArray('toast', t('Manual no encontrado o acceso denegado.'));
            return Redirect::to('/ev/manuales');
        }
        $this->reglas = (new Manuales_reglas)->todas($manuales_idu);
    }

    #
    public function formulario_manual($manuales_idu='')
    {
        $this->manual = (new Manuales)->uno($manuales_idu);
        if ($manuales_idu !== '' && !$this->manual->idu) {
            Session::setArray('toast', t('Manual no encontrado o acceso denegado.'));
            return Redirect::to('/ev/manuales');
        }
        $this->fichas = (new Fichas)->todas();
        $this->plantillas = (new Plantillas)->todas();

        $this->titulo = t('Edición de manual');
        View::template('ventana');
    }

    #
    public function ver($manuales_idu)
    {
        $this->manual = (new Manuales)->uno($manuales_idu);
        if (!$this->manual->idu) {
            Session::setArray('toast', t('Manual no encontrado o acceso denegado.'));
            return Redirect::to('/ev/manuales');
        }
        $this->title = $this->manual->nombre;
        $this->reglas = (new Manuales_reglas)->todas($manuales_idu);
        View::template('impresion');
    }

    #
    public function actualizar()
    {
        $idu = $_POST['idu'] ?? '';
        if (!$this->esEditable($idu)) {
            Session::setArray('toast', t('No tiene permisos para editar este manual.'));
            return Redirect::to('/ev/manuales');
        }
        $manuales_idu = (new Manuales)->actualizar($_POST);
        Redirect::to('/ev/manuales/formulario/' . $manuales_idu);
    }

    #
    public function crear()
    {
        $manuales_idu = (new Manuales)->crear($_POST);
        Redirect::to('/ev/manuales/formulario/' . $manuales_idu);
    }

    #
    /*public function duplicar()
    {
        $manuales_idu = (new Manuales)->duplicar($_POST);
        Redirect::to('/ev/manuales/formulario/' . $manuales_idu);
    }*/

    #
    public function eliminar()
    {
        $idu = $_POST['idu'] ?? '';
        if (!$this->esPropietario($idu)) {
            Session::setArray('toast', t('Solo el creador puede eliminar el manual.'));
            return Redirect::to('/ev/manuales');
        }
        (new Manuales)->eliminar($_POST['idu']);
        Redirect::to('/ev/manuales/formulario');
    }

    # Acciones para manejar las reglas:

    #
    public function formulario_regla($manuales_idu, $manuales_reglas_idu='')
    {
        $this->manual = (new Manuales)->uno($manuales_idu);
        if (!$this->manual->idu) {
            Session::setArray('toast', t('Manual no encontrado o acceso denegado.'));
            return Redirect::to('/ev/manuales');
        }
        $this->manuales_idu = $this->manual->idu;
        $this->reglas = (new Manuales_reglas)->todas($manuales_idu);
        $this->regla = (new Manuales_reglas)->una($manuales_reglas_idu);
        
        $this->confirmar_cierre = true;
        $this->titulo = t('Edición de página');
        View::template('ventana');
    }

    public function regla_actualizar()
    {
        $manuales_idu = $_POST['manuales_idu'] ?? '';
        if (!$this->esEditable($manuales_idu)) {
            Session::setArray('toast', t('No tiene permisos para editar este manual.'));
            return Redirect::to('/ev/manuales');
        }
        $this->reglas_idu = (new Manuales_reglas)->actualizar($_POST);
        Redirect::to($this->obtenerHashReferer());
    }

    #
    public function regla_actualizar_ajax()
    {
        View::select(null);
        View::template(null);
        header('Content-Type: application/json');

        $idu = Input::post('idu');
        if ($idu) {
            $regla = Manuales_reglas::first('SELECT * FROM manuales_reglas WHERE idu=?', [$idu]);
            if ($regla) {
                if (!$this->esEditable($regla->manuales_idu)) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'No tiene permisos de edición.']);
                    return;
                }
                $descripcion = $_POST['descripcion'] ?? null;
                if ($descripcion !== null) {
                    $desc = Manuales_reglas::normalizarDescripcion($descripcion, $idu);
                    $desc = Manuales_reglas::balancearHtml($desc);
                    Manuales_reglas::query(
                        'UPDATE manuales_reglas SET descripcion=?, descripcion_md=? WHERE idu=?',
                        [$desc, _html::bbcode($desc), $idu]
                    );
                    echo json_encode(['success' => true]);
                    return;
                }
            }
        }
        echo json_encode(['success' => false]);
    }

    public function regla_crear_ajax()
    {
        View::select(null);
        View::template(null);
        header('Content-Type: application/json');

        $manuales_idu = Input::post('manuales_idu');
        $referencia_idu = Input::post('referencia_idu');
        $posicion = Input::post('posicion') === 'antes' ? 'antes' : 'despues';

        if ($manuales_idu) {
            if (!$this->esEditable($manuales_idu)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'No tiene permisos de edición.']);
                return;
            }
            
            $referencia = null;
            if ($referencia_idu) {
                $referencia = Manuales_reglas::first('SELECT * FROM manuales_reglas WHERE idu=?', [$referencia_idu]);
            }
            
            if ($referencia) {
                $original_peso = intval($referencia->peso);
                $idioma = $referencia->idioma;

                if ($posicion === 'antes') {
                    Manuales_reglas::query(
                        'UPDATE manuales_reglas SET peso = CAST(peso AS UNSIGNED) + 1 WHERE manuales_idu = ? AND idioma = ? AND CAST(peso AS UNSIGNED) >= ?',
                        [$manuales_idu, $idioma, $original_peso]
                    );
                    $peso = $original_peso;
                } else {
                    Manuales_reglas::query(
                        'UPDATE manuales_reglas SET peso = CAST(peso AS UNSIGNED) + 1 WHERE manuales_idu = ? AND idioma = ? AND CAST(peso AS UNSIGNED) > ?',
                        [$manuales_idu, $idioma, $original_peso]
                    );
                    $peso = $original_peso + 1;
                }
            } else {
                $peso = 0;
                $idioma = 'ES';
            }

            $new_idu = _str::uid();
            $valores = [
                $manuales_idu,
                'Ninguno',
                0,
                $idioma,
                (string)$peso,
                t('Nueva Página'),
                $new_idu,
                '',
                "<article id=\"pag-$new_idu\">\n</article>",
                _html::bbcode("<article id=\"pag-$new_idu\">\n</article>"),
                '',
                '',
                Session::get('idu')
            ];

            $sql = 'INSERT INTO manuales_reglas SET manuales_idu=?, manuales_reglas_idu=?, pagina_nueva=?, idioma=?, peso=?, nombre=?, idu=?, valor=?, descripcion=?, descripcion_md=?, notas=?, fotos=?, fotos_usuarios_idu=?';
            Manuales_reglas::query($sql, $valores);

            echo json_encode(['success' => true, 'idu' => $new_idu, 'nombre' => t('Nueva Página')]);
            return;
        }
        echo json_encode(['success' => false]);
    }

    #
    public function regla_crear()
    {
        $manuales_idu = $_POST['manuales_idu'] ?? '';
        if (!$this->esEditable($manuales_idu)) {
            Session::setArray('toast', t('No tiene permisos para editar este manual.'));
            return Redirect::to('/ev/manuales');
        }
        $this->reglas_idu = (new Manuales_reglas)->crear($_POST);
        Redirect::to($this->obtenerHashReferer());
    }

    #
    public function regla_eliminar()
    {
        $idu = $_POST['idu'] ?? Input::post('idu');
        $regla = (new Manuales_reglas)->una($idu);
        if (!$regla->idu || !$this->esEditable($regla->manuales_idu)) {
            if (Input::isAjax()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'No tiene permisos de edición.']);
                return;
            }
            Session::setArray('toast', t('No tiene permisos para editar este manual.'));
            return Redirect::to('/ev/manuales');
        }
        (new Manuales_reglas)->eliminar($idu);
        if (Input::isAjax()) {
            View::select(null);
            View::template(null);
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            return;
        }
        $ref = parse_url($_SERVER['HTTP_REFERER']);
        Redirect::to($ref['path'] . (isset($ref['fragment']) ? '#' . $ref['fragment'] : ''));
    }

    #
    public function ordenar_reglas()
    {
        View::template(null);
        View::select(null);
        header('Content-Type: application/json');

        $orden = Input::post('orden');
        if (is_array($orden)) {
            $first_idu = reset($orden);
            $regla = (new Manuales_reglas)->una($first_idu);
            if (!$regla->idu || !$this->esEditable($regla->manuales_idu)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'No tiene permisos de edición.']);
                return;
            }
            (new Manuales_reglas)->ordenar($orden);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Datos incorrectos']);
        }
    }

    public function regla_traducir()
    {
        (new Manuales_reglas)->crear($_POST, $_POST['traducir_al']);
        Redirect::to($this->obtenerHashReferer());
    }

    public function check_db()
    {
        View::select(null);
        View::template(null);
        $r = (new Manuales_reglas)->first("idu='5cbdcd7fd6a3'");
        if ($r) {
            echo "=== BEFORE ===\n";
            echo htmlspecialchars($r->descripcion) . "\n\n";
            echo "=== AFTER ===\n";
            echo htmlspecialchars(Manuales_reglas::formatearHtml($r->descripcion)) . "\n\n";
        } else {
            echo "Not found";
        }
    }

    #
    public function guardar_fondo($manuales_idu)
    {
        if (!$this->esEditable($manuales_idu)) {
            if (Input::isAjax()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'No tiene permisos de edición.']);
            } else {
                Session::setArray('toast', t('No tiene permisos para editar este manual.'));
                Redirect::to('/ev/manuales');
            }
            return;
        }
        $manual = (new Manuales)->uno($manuales_idu);
        $plantilla = (new Plantillas)->obtenerOCrearPorNombre($manual->plantilla, Session::get('idu'), $manual->plantilla);
        
        // Si el nombre de la plantilla ha cambiado (porque se ha clonado), lo actualizamos en el manual
        if ($manual->plantilla !== $plantilla->nombre) {
            $manual->plantilla = $plantilla->nombre;
            $manual->save();
        }

        $ajustes = $plantilla->saveSettings($_POST, $_FILES);
        
        if (Input::isAjax()) {
            View::template(null);
            View::select(null);
            header('Content-Type: application/json');
            
            $toasts_html = '';
            if (Session::has('toast')) {
                ob_start();
                View::partial('toast');
                $toasts_html = ob_get_clean();
            }

            echo json_encode([
                'css_url' => $plantilla->getCssUrl(),
                'url' => $ajustes->fondo_url,
                'url_footer' => $ajustes->footer_url,
                'toast' => $toasts_html,
                'fuentes' => $ajustes->tipografia,
                'css_inline' => $plantilla->cssPersonalizado()
            ], JSON_INVALID_UTF8_SUBSTITUTE);
            return;
        }
        
        // Si no es AJAX, redirigimos
        Redirect::to(parse_url($_SERVER['HTTP_REFERER'])['path']);
    }

    # 
    public function editor($idioma, $manuales_idu)
    {
        $this->manual = (new Manuales)->uno($manuales_idu);
        if (!$this->manual->idu) {
            Session::setArray('toast', t('Manual no encontrado o acceso denegado.'));
            return Redirect::to('/ev/manuales');
        }
        $this->reglas = (new Manuales_reglas)->todas($manuales_idu, $idioma);
        $this->manuales_idu = $manuales_idu;
        $this->plantilla_manual = (new Plantillas)->obtenerOCrearPorNombre($this->manual->plantilla, Session::get('idu'), $this->manual->plantilla);
        $this->ajustes = $this->plantilla_manual->getSettings();
        
        $this->format_print = strtoupper($this->manual->formato) . ($this->manual->orientacion === 'h' ? ' landscape' : '');
        $this->format_booklet = strtoupper($this->manual->formato === 'a5' ? 'a4' : ($this->manual->formato === 'a4' ? 'a3' : 'ledger')) . ' landscape';
        $this->format_kdp = Manuales::getKdpFormat($this->manual);
        
        View::template('editor');
    }

    #
    public function plan_de_accion()
    {
        View::template(null);
    }

    public function selector_fuentes($manuales_idu, $nivel = 'h1')
    {
        $this->manuales_idu = $manuales_idu;
        $this->nivel = h($nivel);
        
        $manual = (new Manuales)->uno($manuales_idu);
        $plantilla = (new Plantillas)->obtenerOCrearPorNombre($manual->plantilla, Session::get('idu'), $manual->plantilla);
        $ajustes = $plantilla->getSettings();
        
        $this->fuente_actual = isset($ajustes->tipografia[$this->nivel]) ? $ajustes->tipografia[$this->nivel]['fuente'] : '';
        
        $fuentes_locales = [];
        $css_prev = "";
        foreach (Plantillas::fuentesLocales() as $fuente_local) {
            $nombre_fuente = $fuente_local['nombre'];
            $fuentes_locales[] = $nombre_fuente;
            $css_prev .= "@font-face { font-family: '$nombre_fuente'; src: url('{$fuente_local['url']}') format('woff2'); font-display: swap; }\n";
        }
        
        // Añadir también la fuente actual a la lista si no está y tiene estilos propios
        if ($this->fuente_actual && !in_array($this->fuente_actual, $fuentes_locales)) {
            $fuentes_locales[] = $this->fuente_actual;
            $css_prev .= $ajustes->css_fuentes; // Asegura que la fuente externa se cargue en el modal
        }
        
        $this->fuentes = $fuentes_locales;
        $this->css_previsualizaciones = $css_prev;
        
        $this->titulo = t("Tipografías para " . strtoupper($this->nivel));
        $this->filtro_filas = '.font-item';
        View::template('ventana');
    }

    #
    public function ia_html()
    {
        View::select(null);
        $prompt = Input::post('prompt');
        $manuales_idu = Input::post('manuales_idu');
        if (!$prompt || !$manuales_idu) {
            echo "Error: faltan datos.";
            return;
        }

        if (!$this->esEditable($manuales_idu)) {
            http_response_code(403);
            echo "No tiene permisos de edición.";
            return;
        }

        $manual = (new Manuales)->uno($manuales_idu);
        $reglas = (new Manuales_reglas)->todas($manuales_idu);
        $current_content = Input::post('current_content');
        
        $contexto_manual = "MANUAL: " . $manual->nombre . "\n";
        $contexto_manual .= "ESTRUCTURA Y RESUMEN DE LAS PÁGINAS DEL MANUAL:\n";
        foreach ($reglas as $r) {
            $snippet = trim(preg_replace('/\s+/', ' ', strip_tags($r->descripcion)));
            if (mb_strlen($snippet) > 150) {
                $snippet = mb_substr($snippet, 0, 150) . '...';
            }
            $contexto_manual .= "- PÁGINA: " . $r->nombre . ($snippet !== '' ? " (Resumen: {$snippet})" : "") . "\n";
        }
        $system_instructions = "Actúas como un experto gestor de contenido para manuales de rol.\n";
        $system_instructions .= "INSTRUCCIONES CRÍTICAS DE SALIDA:\n";
        $system_instructions .= "1. Responde EXCLUSIVAMENTE con el fragmento HTML final de la página (dentro de las etiquetas <article>...</article>).\n";
        $system_instructions .= "2. NO incluyas bloques de código markdown (como ```html o ```).\n";
        $system_instructions .= "3. NO añadas introducciones, explicaciones, saludos ni comentarios. Solo la estructura HTML final.\n";
        $system_instructions .= "4. Usa HTML semántico y limpio (<article>, <h1>, <h2>, <ul>, <p>). Evita divitis.\n";
        $system_instructions .= "5. Respeta escrupulosamente la estructura HTML que ya funcione y la jerarquía de los elementos. No rompas etiquetas.\n";
        $system_instructions .= "6. Si el usuario pide una actualización, mantén las partes del contenido que sean correctas y solo modifica o añade lo necesario.\n";

        $prompt_user = "CONTEXTO DEL MANUAL:\n" . $contexto_manual . "\n\n";
        if ($current_content) {
            $prompt_user .= "CONTENIDO ACTUAL DE LA PÁGINA (ÚSALO COMO BASE PARA ACTUALIZAR):\n" . $current_content . "\n\n";
        }
        $prompt_user .= "PETICIÓN DEL USUARIO:\n" . $prompt;

        $respuesta = _ia::ask($prompt_user, $system_instructions);
        
        if (!empty($respuesta->output_text)) {
            $html = trim($respuesta->output_text);
            
            // 1) Si contiene bloques de código markdown, extraemos su contenido
            if (preg_match('/```(?:html)?\s*(.*?)\s*```/is', $html, $m)) {
                $html = trim($m[1]);
            }
            
            // 2) Extraemos únicamente el bloque <article>...</article> si existe
            if (preg_match('/(<article\b.*?>.*?<\/article>)/is', $html, $m)) {
                $html = trim($m[1]);
                echo $html;
            } else {
                http_response_code(400);
                echo $html;
            }
        } else {
            http_response_code(400);
            echo "No se pudo obtener respuesta de la IA.";
        }
    }

    private function obtenerHashReferer()
    {
        $hash = '';
        if (!empty($_POST['descripcion'])) {
            if (preg_match('/<article\b[^>]*\bid=["\']([^"\']+)["\']/i', $_POST['descripcion'], $matches)) {
                $hash = $matches[1];
            }
        }

        $ref = parse_url($_SERVER['HTTP_REFERER']);
        $path = isset($ref['path']) ? $ref['path'] : '';

        if ($hash === '') {
            $hash = isset($ref['fragment']) ? $ref['fragment'] : '';
        }

        return $path . ($hash !== '' ? '#' . $hash : '');
    }

    private function esEditable($manuales_idu)
    {
        if (empty($manuales_idu)) {
            return true;
        }
        $manual = (new Manuales)->uno($manuales_idu);
        if (!$manual->idu) {
            return false;
        }
        if ($manual->usuarios_idu === Session::get('idu')) {
            return true;
        }
        return $manual->alcance === 'editor';
    }

    private function esPropietario($manuales_idu)
    {
        if (empty($manuales_idu)) {
            return false;
        }
        $manual = (new Manuales)->uno($manuales_idu);
        return ($manual && $manual->idu && $manual->usuarios_idu === Session::get('idu'));
    }
}


