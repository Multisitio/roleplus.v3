<?php
/**
 * Modelo para las Plantillas de los Manuales.
 */
class Plantillas extends LiteRecord
{
    const DEFAULTS = [
        '--background-color' => 'white',
        '--background-image' => 'none',
        '--background-color-even' => 'white',
        '--background-image-even' => 'none',
        '--margin-asymmetric' => '0',
        '--blockquote-border-left-color' => 'transparent',
        '--blockquote-border-left-width' => '0',
        '--blockquote-margin-bottom' => '0',
        '--blockquote-margin-left' => '5',
        '--blockquote-margin-right' => '0',
        '--blockquote-margin-top' => '0',
        '--blockquote-padding-bottom' => '10',
        '--blockquote-padding-left' => '10',
        '--blockquote-padding-right' => '15',
        '--blockquote-padding-top' => '10',
        '--body-align' => 'left',
        '--body-color' => '#000000',
        '--body-decoration' => '0',
        '--body-family' => 'inherit',
        '--body-size' => '14px',
        '--body-style' => 'normal',
        '--body-transform' => 'none',
        '--body-variant' => 'normal',
        '--footer-height' => '0',
        '--footer-imagen' => 'none',
        '--footer-margin-bottom' => '0',
        '--footer-margin-left' => '0',
        '--footer-margin-right' => '0',
        '--footer-margin-top' => '0',
        '--footer-padding-bottom' => '20',
        '--footer-padding-left' => '20',
        '--footer-padding-right' => '20',
        '--footer-padding-top' => '10',
        '--footer-page-position' => 'outside',
        '--footer-page-x' => '0',
        '--footer-page-y' => '0',
        '--h1-align' => 'left',
        '--h1-color' => '#000000',
        '--h1-decoration' => '0',
        '--h1-family' => 'inherit',
        '--h1-size' => '36px',
        '--h1-style' => 'normal',
        '--h1-transform' => 'none',
        '--h1-variant' => 'small-caps',
        '--h2-align' => 'left',
        '--h2-color' => '#000000',
        '--h2-decoration' => '0',
        '--h2-family' => 'inherit',
        '--h2-size' => '30',
        '--h2-style' => 'normal',
        '--h2-transform' => 'none',
        '--h2-variant' => 'small-caps',
        '--h3-align' => 'left',
        '--h3-color' => '#000000',
        '--h3-decoration' => '0',
        '--h3-family' => 'inherit',
        '--h3-size' => '24px',
        '--h3-style' => 'normal',
        '--h3-transform' => 'none',
        '--h3-variant' => 'small-caps',
        '--h4-align' => 'left',
        '--h4-color' => '#000000',
        '--h4-decoration' => '0',
        '--h4-family' => 'inherit',
        '--h4-size' => '20',
        '--h4-style' => 'normal',
        '--h4-transform' => 'none',
        '--h4-variant' => 'small-caps',
        '--h5-align' => 'left',
        '--h5-color' => '#000000',
        '--h5-decoration' => '0',
        '--h5-family' => 'inherit',
        '--h5-size' => '18px',
        '--h5-style' => 'normal',
        '--h5-transform' => 'none',
        '--h5-variant' => 'small-caps',
        '--h6-align' => 'left',
        '--h6-color' => '#000000',
        '--h6-decoration' => '0',
        '--h6-family' => 'inherit',
        '--h6-size' => '16px',
        '--h6-style' => 'normal',
        '--h6-transform' => 'none',
        '--h6-variant' => 'small-caps',
        '--header-margin-bottom' => '0',
        '--header-margin-left' => '0',
        '--header-margin-right' => '0',
        '--header-margin-top' => '0',
        '--header-padding-bottom' => '10',
        '--header-padding-left' => '20',
        '--header-padding-right' => '20',
        '--header-padding-top' => '20',
        '--list-margin-bottom' => '0',
        '--list-margin-left' => '0',
        '--list-margin-right' => '0',
        '--list-margin-top' => '0',
        '--list-padding-bottom' => '10',
        '--list-padding-left' => '20',
        '--list-padding-right' => '0',
        '--list-padding-top' => '0',
        '--list-style-bullet' => "'✦ '",
        '--list-style-checkbox' => "'☐ '",
        '--list-style-radio' => "'🔾 '",
        '--list-style-sub-bullet' => "'⮞ '",
        '--list-style-ordered' => 'decimal',
        '--list-style-ordered-sub' => 'lower-latin',
        '--p-margin-bottom' => '0',
        '--p-margin-left' => '0',
        '--p-margin-right' => '0',
        '--p-margin-top' => '0',
        '--p-padding-bottom' => '10',
        '--p-padding-left' => '0',
        '--p-padding-right' => '0',
        '--p-padding-top' => '10',
        '--p-gap' => '0',
        '--section-margin-bottom' => '0',
        '--section-margin-left' => '0',
        '--section-margin-right' => '0',
        '--section-margin-top' => '0',
        '--section-padding-bottom' => '10',
        '--section-padding-left' => '20',
        '--section-padding-right' => '20',
        '--section-padding-top' => '10',
        '--small-align' => 'left',
        '--small-color' => '#000000',
        '--small-decoration' => '0',
        '--small-family' => 'inherit',
        '--small-size' => '80%',
        '--small-style' => 'normal',
        '--small-transform' => 'none',
        '--small-variant' => 'normal',
        '--table-th-border-bottom-width' => '0',
        '--table-th-border-bottom-color' => 'transparent',
        '--table-col1-border-right-width' => '0',
        '--table-col1-border-right-color' => 'transparent',
        '--table-zebra-opacity' => '0',
        '--table-zebra-color' => 'transparent',
        '--table-td-padding-x' => '0',
        '--table-td-padding-y' => '0',
        '--table-margin-top' => '10',
        '--table-margin-right' => '0',
        '--table-margin-bottom' => '10',
        '--table-margin-left' => '0',
        '--a-color' => 'inherit',
        '--dropcap-family' => 'inherit',
        '--dropcap-size' => '0px',
        '--dropcap-color' => 'inherit',
        '--dropcap-margin-top' => '0px',
        '--dropcap-margin-right' => '0px',
        '--dropcap-margin-bottom' => '0px',
        '--dropcap-margin-left' => '0px',
        '--dropcap-align' => 'left',
        '--dropcap-decoration' => '0',
        '--dropcap-weight' => 'normal',
        '--dropcap-style' => 'normal',
        '--dropcap-transform' => 'none',
        '--dropcap-variant' => 'normal',
    ];

    const MENU_SECCIONES = [
        'header'  => 'Encabezado',
        'section' => 'Cuerpo',
        'footer'  => 'Pie',
    ];

    const MENU_LADOS = ['top', 'right', 'bottom', 'left'];

    const MENU_FOOTER = [
        'footer_page_x' => ['label' => 'Numero X', 'css' => '--footer-page-x', 'unit' => 'px'],
        'footer_page_y' => ['label' => 'Numero Y', 'css' => '--footer-page-y', 'unit' => 'px'],
    ];

    const MENU_LISTAS = [
        'bullet_ul'       => ['label' => 'Lista des.', 'css' => '--list-style-bullet'],
        'bullet_ul_ul'    => ['label' => 'Sublista des.', 'css' => '--list-style-sub-bullet'],
        'bullet_checkbox' => ['label' => 'Casilla', 'css' => '--list-style-checkbox'],
        'bullet_radio'    => ['label' => 'Opción', 'css' => '--list-style-radio'],
        'bullet_ol'       => ['label' => 'Lista ord.', 'css' => '--list-style-ordered'],
        'bullet_ol_sub'   => ['label' => 'Sublista ord.', 'css' => '--list-style-ordered-sub'],
    ];

    const TIPOS_LISTA_ORDENADA = [
        'decimal' => '1, 2, 3...',
        'decimal-leading-zero' => '01, 02, 03...',
        'lower-latin' => 'a, b, c...',
        'upper-latin' => 'A, B, C...',
        'lower-roman' => 'i, ii, iii...',
        'upper-roman' => 'I, II, III...',
    ];

    const MENU_TIPOGRAFIA_CONTROLES = [
        'variant' => [
            ''            => 'Normal',
            'bold'        => 'Negrita',
            'italic'      => 'Cursiva',
            'bold-italic' => 'Neg. Cur.',
        ],
        'align' => [
            'left'    => 'Izq.',
            'center'  => 'Centro',
            'right'   => 'Der.',
            'justify' => 'Just.',
        ],
        'transform' => [
            'none'       => 'Personalizado',
            'uppercase'  => 'MAYUSCULAS',
            'lowercase'  => 'minusculas',
            'capitalize' => 'Capitalizar',
            'small-caps' => 'Small caps',
        ],
        'decoration' => [
            '0'   => 'Sin subrayar',
            '1px' => '1px',
            '2px' => '2px',
            '3px' => '3px',
        ],
    ];

    /**
     * Obtiene una plantilla por nombre para un usuario, o la crea si no existe.
     */
    public function obtenerOCrearPorNombre($nombre, $idu_usuario, $nombre_original = '')
    {
        $nombre = trim((string)$nombre);
        if (!$nombre) $nombre = 'Por defecto';
        $idu_usuario = trim((string)$idu_usuario);
        if (!$idu_usuario) $idu_usuario = (string)Session::get('idu');

        $p = $this->first('SELECT * FROM plantillas WHERE nombre=? AND usuarios_idu=?', [$nombre, $idu_usuario]);

        if (!$p) {
            $idu = _str::uid('plt');
            $this->query(
                'INSERT INTO plantillas SET idu=?, usuarios_idu=?, nombre=?, creado=?',
                [$idu, $idu_usuario, $nombre, date('Y-m-d H:i:s')]
            );
            $p = $this->first('SELECT * FROM plantillas WHERE idu=?', [$idu]);
            if (!$p) return parent::cols();

            // Si hay un nombre original, duplicamos sus reglas desde la versión "maestra" o de otro usuario
            if ($nombre_original) {
                // Buscamos la plantilla original (puede ser la misma por nombre pero sin dueño, o de otro usuario si se especificó)
                $original = $this->first('SELECT idu FROM plantillas WHERE nombre=? AND usuarios_idu != ? AND idu != ? ORDER BY usuarios_idu ASC LIMIT 1', [$nombre_original, $idu_usuario, $p->idu]);
                if ($original) {
                    (new Plantillas_reglas)->duplicar($original->idu, $p->idu);
                }
            }
        }
        return $p;
    }

    /**
     * Obtiene todas las plantillas disponibles (del sistema o del usuario actual) para mostrarlas en selectores o datalists.
     */
    public function todas()
    {
        $sql = "SELECT DISTINCT nombre FROM plantillas WHERE usuarios_idu=? OR usuarios_idu IS NULL ORDER BY nombre";
        return self::all($sql, [Session::get('idu')]);
    }

    public static function menuPlantilla()
    {
        return [
            'footer' => self::MENU_FOOTER,
            'listas' => self::MENU_LISTAS,
            'secciones' => self::MENU_SECCIONES,
            'lados' => self::MENU_LADOS,
            'niveles_tipografia' => self::NIVELES_TIPOGRAFIA,
            'tipografia_controles' => self::MENU_TIPOGRAFIA_CONTROLES,
        ];
    }

    public static function defaultCssVariables()
    {
        return self::DEFAULTS;
    }

    /**
     * Retorna todos los ajustes de la plantilla (Typography, Background, Footer).
     * Toda la lógica de obtención está centralizada aquí para evitar SQL en las vistas.
     */
    public function getSettings()
    {
        $Reglas = new Plantillas_reglas;
        $ajustes = new stdClass();
        $vars = $Reglas->leer($this->idu, self::defaultCssVariables());

        $ajustes->fondo_url = '';
        if (isset($vars['--background-image']) && preg_match('/url\([\'"]?(.*?)[\'"]?\)/', $vars['--background-image'], $m)) {
            $ajustes->fondo_url = $m[1];
        }

        $ajustes->fondo_even_url = '';
        if (isset($vars['--background-image-even']) && preg_match('/url\([\'"]?(.*?)[\'"]?\)/', $vars['--background-image-even'], $m)) {
            $ajustes->fondo_even_url = $m[1];
        }

        $ajustes->margin_asymmetric = intval($vars['--margin-asymmetric'] ?? 0);

        $ajustes->footer_url = '';
        if (isset($vars['--footer-imagen']) && preg_match('/url\([\'"]?(.*?)[\'"]?\)/', $vars['--footer-imagen'], $m)) {
            $ajustes->footer_url = $m[1];
        }

        foreach (self::MENU_FOOTER as $name => $field) {
            $ajustes->$name = intval($vars[$field['css']]);
        }
        $ajustes->footer_page_position = $vars['--footer-page-position'];
        $ajustes->footer_height = intval($vars['--footer-height']);

        $secciones_ajustes = array_merge(array_keys(self::MENU_SECCIONES), ['p', 'list', 'blockquote']);
        foreach ($secciones_ajustes as $sec) {
            foreach (self::MENU_LADOS as $lado) {
                $prop_p = "padding_{$sec}_{$lado}";
                $ajustes->$prop_p = intval($vars["--{$sec}-padding-{$lado}"]);

                $prop_m = "margin_{$sec}_{$lado}";
                $ajustes->$prop_m = intval($vars["--{$sec}-margin-{$lado}"]);
            }
        }

        foreach (self::MENU_LADOS as $lado) {
            $prop_m = "margin_table_{$lado}";
            $ajustes->$prop_m = intval($vars["--table-margin-{$lado}"]);
        }

        foreach (self::MENU_LISTAS as $name => $field) {
            $ajustes->$name = trim($vars[$field['css']], "'\" ");
        }

        $ajustes->tipografia = [];
        foreach (self::NIVELES_TIPOGRAFIA as $niv) {
            $fuente = trim($vars["--{$niv}-family"], "'\" ");
            $ajustes->tipografia[$niv] = [
                'fuente'     => $fuente === 'inherit' ? '' : $fuente,
                'size'       => $vars["--{$niv}-size"],
                'color'      => $vars["--{$niv}-color"],
                'align'      => $vars["--{$niv}-align"],
                'decoration' => $vars["--{$niv}-decoration"],
                'weight'     => $vars["--{$niv}-weight"],
                'style'      => $vars["--{$niv}-style"],
                'transform'  => ($vars["--{$niv}-variant"] === 'small-caps' ? 'small-caps' : $vars["--{$niv}-transform"]),
                'variant'    => $vars["--{$niv}-variant"],
            ];
        }

        $ajustes->table_th_border_bottom_width = $vars['--table-th-border-bottom-width'] ?? '0';
        $ajustes->table_th_border_bottom_color = $vars['--table-th-border-bottom-color'] ?? 'transparent';
        $ajustes->table_col1_border_right_width = $vars['--table-col1-border-right-width'] ?? '0';
        $ajustes->table_col1_border_right_color = $vars['--table-col1-border-right-color'] ?? 'transparent';
        $ajustes->table_zebra_opacity = intval($vars['--table-zebra-opacity'] ?? '0');
        $ajustes->table_zebra_color = $vars['--table-zebra-color'] ?? 'transparent';
        $ajustes->table_td_padding_x = $vars['--table-td-padding-x'] ?? '0';
        $ajustes->table_td_padding_y = $vars['--table-td-padding-y'] ?? '0';
        $ajustes->blockquote_border_left_width = $vars['--blockquote-border-left-width'] ?? '0';
        $ajustes->blockquote_border_left_color = $vars['--blockquote-border-left-color'] ?? 'transparent';
        $ajustes->a_color = $vars['--a-color'] ?? 'inherit';
        $ajustes->p_gap = intval($vars['--p-gap'] ?? '0');

        $ajustes->dropcap_family = trim($vars['--dropcap-family'] ?? 'inherit', "'\" ");
        if ($ajustes->dropcap_family === 'inherit') $ajustes->dropcap_family = '';
        $ajustes->dropcap_size = $vars['--dropcap-size'] ?? '0px';
        $ajustes->dropcap_color = $vars['--dropcap-color'] ?? 'inherit';
        $ajustes->dropcap_margin_top = $vars['--dropcap-margin-top'] ?? '0px';
        $ajustes->dropcap_margin_right = $vars['--dropcap-margin-right'] ?? '0px';
        $ajustes->dropcap_margin_bottom = $vars['--dropcap-margin-bottom'] ?? '0px';
        $ajustes->dropcap_margin_left = $vars['--dropcap-margin-left'] ?? '0px';

        $fuentes_todas = array_column($ajustes->tipografia, 'fuente');
        if ($ajustes->dropcap_family) {
            $fuentes_todas[] = $ajustes->dropcap_family;
        }
        $ajustes->css_fuentes = $this->generarCssFuentes($fuentes_todas);

        return $ajustes;
    }

    /**
     * Guarda de forma centralizada todos los ajustes enviados desde el editor.
     * Implementa el "CRUD intocable": Upsert de variables CSS.
     */
    public function saveSettings($post, $files)
    {
        $Reglas = new Plantillas_reglas;
        $debe_compilar = false;
        $Reglas->eliminar_obsoletas($this->idu);

        // 1. Tipografía
        foreach (self::NIVELES_TIPOGRAFIA as $niv) {
            // Fuente
            if (isset($post["fuente_{$niv}"])) {
                $fuente = trim($post["fuente_{$niv}"]);
                if ($fuente) {
                    $fuente = self::normalizarNombreFuente($fuente);
                    $forzar = !empty($post['forzar_descarga_fuente']);
                    if (self::descargarFuente($fuente, $forzar)) {
                        $Reglas->guardar_una($this->idu, '.plantilla', "--{$niv}-family", "'$fuente'");
                        $debe_compilar = true;
                    }
                } else {
                    $Reglas->query("DELETE FROM plantillas_reglas WHERE plantillas_idu=? AND selector='.plantilla' AND propiedad=?", [$this->idu, "--{$niv}-family"]);
                    $debe_compilar = true;
                }
            }

            // Atributos numéricos y selects
            $props = ['size', 'color', 'align', 'variant', 'transform', 'decoration'];
            foreach ($props as $p) {
                $key = "{$p}_{$niv}";
                if (isset($post[$key])) {
                    $val = trim($post[$key]);
                    if ($p === 'size' && is_numeric($val)) {
                        $val .= ($niv === 'small') ? '%' : 'px';
                    }
                    
                    if ($p === 'variant') {
                        $Reglas->guardar_una($this->idu, '.plantilla', "--{$niv}-weight", (str_contains($val, 'bold') ? 'bold' : 'normal'));
                        $Reglas->guardar_una($this->idu, '.plantilla', "--{$niv}-style", (str_contains($val, 'italic') ? 'italic' : 'normal'));
                    } elseif ($p === 'transform') {
                        $Reglas->guardar_una($this->idu, '.plantilla', "--{$niv}-transform", ($val === 'small-caps' ? 'none' : $val));
                        $Reglas->guardar_una($this->idu, '.plantilla', "--{$niv}-variant", ($val === 'small-caps' ? 'small-caps' : 'normal'));
                    } else {
                        $Reglas->guardar_una($this->idu, '.plantilla', "--{$niv}-{$p}", $val);
                    }
                    $debe_compilar = true;
                }
            }
        }

        // 1. Footer (Posiciones y texto)
        foreach (self::MENU_FOOTER as $p => $field) {
            if (isset($post[$p])) {
                $val = trim($post[$p]);
                if (($field['unit'] ?? '') === 'px' && is_numeric($val)) $val .= 'px';
                $Reglas->guardar($this->idu, $field['css'], $val);
                $debe_compilar = true;
            }
        }

        // 3. Paddings y Márgenes
        $secciones_guardar = array_merge(array_keys(self::MENU_SECCIONES), ['p', 'list', 'blockquote']);
        foreach ($secciones_guardar as $sec) {
            foreach (self::MENU_LADOS as $lado) {
                // Paddings
                $key_p = "padding_{$sec}_{$lado}";
                if (isset($post[$key_p])) {
                    $val = intval($post[$key_p]) . 'px';
                    $Reglas->guardar($this->idu, "--{$sec}-padding-{$lado}", $val);
                    $debe_compilar = true;
                }

                // Márgenes
                $key_m = "margin_{$sec}_{$lado}";
                if (isset($post[$key_m])) {
                    $val = intval($post[$key_m]) . 'px';
                    $Reglas->guardar($this->idu, "--{$sec}-margin-{$lado}", $val);
                    $debe_compilar = true;
                }
            }
        }

        // Distancia entre párrafos
        if (isset($post['p_gap'])) {
            $val = intval($post['p_gap']) . 'px';
            $Reglas->guardar($this->idu, '--p-gap', $val);
            $debe_compilar = true;
        }

        foreach (self::MENU_LADOS as $lado) {
            $key_m = "margin_table_{$lado}";
            if (isset($post[$key_m])) {
                $val = intval($post[$key_m]) . 'px';
                $Reglas->guardar($this->idu, "--table-margin-{$lado}", $val);
                $debe_compilar = true;
            }
        }

        // Distancia entre tablas
        if (isset($post['table_gap'])) {
            $val = intval($post['table_gap']) . 'px';
            $Reglas->guardar($this->idu, '--table-gap', $val);
            $debe_compilar = true;
        }

        // 4. Listas
        foreach (self::MENU_LISTAS as $p => $field) {
            if (isset($post[$p])) {
                $val = trim($post[$p]);
                if ($p === 'bullet_ol' || $p === 'bullet_ol_sub') {
                    $Reglas->guardar($this->idu, $field['css'], $val);
                } else {
                    $Reglas->guardar($this->idu, $field['css'], "'$val '");
                }
                $debe_compilar = true;
            }
        }

        // 4.5. Tablas
        $props_tablas = [
            'table_th_border_bottom_width' => '--table-th-border-bottom-width',
            'table_th_border_bottom_color' => '--table-th-border-bottom-color',
            'table_col1_border_right_width' => '--table-col1-border-right-width',
            'table_col1_border_right_color' => '--table-col1-border-right-color',
            'table_zebra_opacity' => '--table-zebra-opacity',
            'table_zebra_color' => '--table-zebra-color',
            'table_td_padding_x' => '--table-td-padding-x',
            'table_td_padding_y' => '--table-td-padding-y',
        ];
        foreach ($props_tablas as $post_key => $css_var) {
            if (isset($post[$post_key])) {
                $val = trim($post[$post_key]);
                if ((str_ends_with($post_key, '_width') || str_ends_with($post_key, '_padding') || str_ends_with($post_key, '_x') || str_ends_with($post_key, '_y')) && is_numeric($val)) {
                    $val .= 'px';
                }
                $Reglas->guardar($this->idu, $css_var, $val);
                $debe_compilar = true;
            }
        }

        // 4.6. Citas
        $props_blockquote = [
            'blockquote_border_left_width' => '--blockquote-border-left-width',
            'blockquote_border_left_color' => '--blockquote-border-left-color',
        ];
        foreach ($props_blockquote as $post_key => $css_var) {
            if (isset($post[$post_key])) {
                $val = trim($post[$post_key]);
                if (str_ends_with($post_key, '_width') && is_numeric($val)) {
                    $val .= 'px';
                }
                $Reglas->guardar($this->idu, $css_var, $val);
                $debe_compilar = true;
            }
        }

        // 4.7. Enlaces
        if (isset($post['a_color'])) {
            $Reglas->guardar($this->idu, '--a-color', trim($post['a_color']));
            $debe_compilar = true;
        }

        // 4.8. Letra Capitular (Dropcap Margins)
        foreach (['top', 'right', 'bottom', 'left'] as $lado) {
            $key_m = "margin_dropcap_{$lado}";
            if (isset($post[$key_m])) {
                $val = intval($post[$key_m]) . 'px';
                $Reglas->guardar($this->idu, "--dropcap-margin-{$lado}", $val);
                $debe_compilar = true;
            }
        }

        // 4.9. Márgenes Asimétricos
        if (isset($post['margin_asymmetric'])) {
            $Reglas->guardar($this->idu, '--margin-asymmetric', intval($post['margin_asymmetric']) ? '1' : '0');
            $debe_compilar = true;
        }

        // 5. Imagenes (Fondo y Footer)
        if (isset($files['fondo_pergamino']) && $files['fondo_pergamino']['tmp_name']) {
            $url = $this->subirImagen('fondo_pergamino', 'fondo');
            if ($url) {
                $Reglas->guardar($this->idu, '--background-image', "url('$url')");
                $debe_compilar = true;
            }
        } elseif (isset($post['fondo_pergamino']) && $post['fondo_pergamino'] === '') {
            $Reglas->eliminar_var($this->idu, '--background-image');
            $debe_compilar = true;
        }

        if (isset($files['fondo_pergamino_even']) && $files['fondo_pergamino_even']['tmp_name']) {
            $url = $this->subirImagen('fondo_pergamino_even', 'fondo_even');
            if ($url) {
                $Reglas->guardar($this->idu, '--background-image-even', "url('$url')");
                $debe_compilar = true;
            }
        } elseif (isset($post['fondo_pergamino_even']) && $post['fondo_pergamino_even'] === '') {
            $Reglas->eliminar_var($this->idu, '--background-image-even');
            $debe_compilar = true;
        }

        if (isset($files['footer_imagen']) && $files['footer_imagen']['tmp_name']) {
            $img_info = @getimagesize($files['footer_imagen']['tmp_name']);
            $url = $this->subirImagen('footer_imagen', 'footer');
            if ($url) {
                $Reglas->guardar($this->idu, '--footer-imagen', "url('$url')");
                if ($img_info && isset($img_info[1])) {
                    $Reglas->guardar($this->idu, '--footer-height', $img_info[1] . 'px');
                }
                $debe_compilar = true;
            }
        } elseif (isset($post['footer_imagen']) && $post['footer_imagen'] === '') {
            $Reglas->eliminar_var($this->idu, '--footer-imagen');
            $Reglas->eliminar_var($this->idu, '--footer-height');
            $debe_compilar = true;
        }

        if ($debe_compilar) $this->compilar();

        return $this->getSettings();
    }

    /**
     * Compila las variables de la base de datos a un archivo CSS.
     */
    public function cssPersonalizado()
    {
        $Reglas = new Plantillas_reglas;
        return $this->generarCssPlantilla($Reglas->leer($this->idu, self::defaultCssVariables()));
    }

    public function compilar()
    {
        $Reglas = new Plantillas_reglas;
        $Reglas->eliminar_obsoletas($this->idu);
        $css = $this->generarCssPlantilla($Reglas->leer($this->idu, self::defaultCssVariables()));

        $dir_base = $_SERVER['DOCUMENT_ROOT'] ?: (defined('PUB_PATH') ? PUB_PATH : '.');
        $dir_base = rtrim($dir_base, '\\/');
        $rel_path = 'css/plantillas/' . $this->idu . '.css';
        $ruta_absoluta = $dir_base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel_path);

        $folder = dirname($ruta_absoluta);
        if (!is_dir($folder)) @mkdir($folder, 0775, true);

        if (file_put_contents($ruta_absoluta, $css) === false) {
            Session::setArray('toast', "Error: No se pudo escribir el archivo CSS en $rel_path. Verifique permisos.");
            return;
        }

        $script = dirname($dir_base) . '/web/javascript/minify.js';
        if (file_exists($script)) {
            $node = 'node';
            if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                if (!shell_exec('which node')) {
                    if (file_exists('/usr/bin/nodejs')) $node = 'nodejs';
                    else if (file_exists('/usr/local/bin/node')) $node = '/usr/local/bin/node';
                    else $node = null;
                }
            }

            if ($node) {
                @exec($node . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($ruta_absoluta));
            }
        }
    }

    private function generarCssPlantilla(array $variables)
    {
        $css = "/* Compilado: " . date('Y-m-d H:i:s') . " */\n";
        $css .= $this->generarCssFuentes($this->fuentesUsadas($variables));
        $css .= $this->cssVariables($variables);
        $css .= $this->cssTipografia();
        $css .= $this->cssEstructuraPlantilla($variables);
        return $css;
    }

    private function fuentesUsadas(array $variables)
    {
        $fuentes = [];
        foreach (self::NIVELES_TIPOGRAFIA as $niv) {
            $key = '--' . $niv . '-family';
            $f = trim($variables[$key] ?? '', "'\" ");
            if ($f) $fuentes[$f] = true;
        }
        $f_drop = trim($variables['--dropcap-family'] ?? '', "'\" ");
        if ($f_drop && $f_drop !== 'inherit') {
            $fuentes[$f_drop] = true;
        }
        return array_keys($fuentes);
    }

    private function cssVariables(array $variables)
    {
        $css = "main {\n";
        foreach ($variables as $p => $v) {
            $css .= '    ' . $p . ': ' . $v . ";\n";
        }
        $zebra_opacity = intval($variables['--table-zebra-opacity'] ?? '0');
        $zebra_color = $variables['--table-zebra-color'] ?? 'transparent';
        if ($zebra_opacity > 0 && $zebra_color !== 'transparent') {
            $css .= "    --table-zebra-bg: color-mix(in srgb, {$zebra_color} {$zebra_opacity}%, transparent);\n";
        } else {
            $css .= "    --table-zebra-bg: transparent;\n";
        }
        return $css . "}\n\n";
    }

    private function cssTipografia()
    {
        $css = '';
        foreach (self::SELECTORES_TIPOGRAFIA as $niv => $sel) {
            $css .= $sel . " {\n";
            $css .= '    font-family: var(--' . $niv . "-family);\n";
            $css .= '    font-size: var(--' . $niv . "-size);\n";
            $css .= '    font-weight: var(--' . $niv . "-weight);\n";
            $css .= '    font-style: var(--' . $niv . "-style);\n";
            $css .= '    text-align: var(--' . $niv . "-align);\n";
            $css .= '    text-transform: var(--' . $niv . "-transform);\n";
            $css .= '    font-variant: var(--' . $niv . "-variant);\n";
            $css .= "    text-decoration: none !important;\n";
            $css .= '    color: var(--' . $niv . "-color);\n";
            $css .= "}\n\n";

            if ($niv !== 'body' && $niv !== 'small') {
                $css .= $sel . "::after {\n";
                $css .= "    content: '';\n";
                $css .= "    display: block;\n";
                $css .= "    width: 100%;\n";
                $css .= "    height: 0;\n";
                $css .= '    border-top: var(--' . $niv . "-decoration) solid currentColor;\n";
                $css .= "    margin-block: -8px 8px;\n";
                $css .= "}\n\n";
            }
        }

        // Vincular footer con h6
        $css .= "main.plantilla div article footer, main.plantilla > div footer, main.plantilla footer {\n";
        $css .= "    font-family: var(--h6-family);\n";
        $css .= "    font-size: var(--h6-size);\n";
        $css .= "    font-weight: var(--h6-weight);\n";
        $css .= "    font-style: var(--h6-style);\n";
        $css .= "    text-transform: var(--h6-transform);\n";
        $css .= "    font-variant: var(--h6-variant);\n";
        $css .= "    color: var(--h6-color);\n";
        $css .= "}\n\n";

        // Vincular caption con h5
        $css .= "main.plantilla caption {\n";
        $css .= "    font-family: var(--h5-family);\n";
        $css .= "    font-size: var(--h5-size);\n";
        $css .= "    font-weight: var(--h5-weight);\n";
        $css .= "    font-style: var(--h5-style);\n";
        $css .= "    text-transform: var(--h5-transform);\n";
        $css .= "    font-variant: var(--h5-variant);\n";
        $css .= "    color: var(--h5-color);\n";
        $css .= "}\n\n";

        // Vincular tfoot con small
        $css .= "main table tfoot td, main table tfoot th {\n";
        $css .= "    font-family: var(--small-family);\n";
        $css .= "    font-size: var(--small-size);\n";
        $css .= "    font-weight: var(--small-weight);\n";
        $css .= "    font-style: var(--small-style);\n";
        $css .= "    text-align: var(--small-align);\n";
        $css .= "    text-transform: var(--small-transform);\n";
        $css .= "    font-variant: var(--small-variant);\n";
        $css .= "    color: var(--small-color);\n";
        $css .= "}\n\n";

        return $css;
    }

    private function cssEstructuraPlantilla(array $variables = [])
    {
        $css = "main > div {\n";
        $css .= "    background-image: var(--background-image);\n";
        $css .= "    background-color: var(--background-color);\n";
        $css .= "    background-repeat: no-repeat;\n";
        $css .= "    background-size: 100% 100%;\n";
        $css .= "    position: relative;\n";
        $css .= "    box-sizing: border-box;\n";
        $css .= "}\n\n";

        // Par e Impar backgrounds
        $css .= "main > div:nth-child(odd) {\n";
        $css .= "    background-image: var(--background-image);\n";
        $css .= "    background-color: var(--background-color);\n";
        $css .= "}\n\n";

        $bg_even = (isset($variables['--background-image-even']) && $variables['--background-image-even'] !== 'none') ? 'var(--background-image-even)' : 'var(--background-image)';
        $bg_color_even = (isset($variables['--background-color-even']) && $variables['--background-color-even'] !== 'white') ? 'var(--background-color-even)' : 'var(--background-color)';

        $css .= "main > div:nth-child(even) {\n";
        $css .= "    background-image: {$bg_even};\n";
        $css .= "    background-color: {$bg_color_even};\n";
        $css .= "}\n\n";

        // Asymmetric margins swap
        if (isset($variables['--margin-asymmetric']) && $variables['--margin-asymmetric'] == '1') {
            $css .= "main > div:nth-child(even) {\n";
            $secciones_ajustes = array_merge(array_keys(self::MENU_SECCIONES), ['p', 'list', 'blockquote']);
            foreach ($secciones_ajustes as $sec) {
                $pad_left = $variables["--{$sec}-padding-left"] ?? '0px';
                $pad_right = $variables["--{$sec}-padding-right"] ?? '0px';
                $mar_left = $variables["--{$sec}-margin-left"] ?? '0px';
                $mar_right = $variables["--{$sec}-margin-right"] ?? '0px';

                $css .= "    --{$sec}-padding-left: {$pad_right};\n";
                $css .= "    --{$sec}-padding-right: {$pad_left};\n";
                $css .= "    --{$sec}-margin-left: {$mar_right};\n";
                $css .= "    --{$sec}-margin-right: {$mar_left};\n";
            }
            $tab_left = $variables["--table-margin-left"] ?? '0px';
            $tab_right = $variables["--table-margin-right"] ?? '0px';
            $css .= "    --table-margin-left: {$tab_right};\n";
            $css .= "    --table-margin-right: {$tab_left};\n";
            $css .= "}\n\n";
        }

        $css .= $this->cssEspaciado('main header', 'header');
        $css .= $this->cssEspaciado('main section', 'section');
        $css .= $this->cssEspaciado('main p', 'p');
        $css .= "main p + p { margin-top: var(--p-gap); }\n";
        $css .= $this->cssEspaciado('main blockquote', 'blockquote');
        $css .= $this->cssEspaciado('main :is(ul, ol)', 'list', "    list-style: none !important;\n");
        $css .= "main :not(main):has(+ :is(ul, ol)) {\n";
        $css .= "    margin-bottom: 0;\n";
        $css .= "    padding-bottom: 0;\n";
        $css .= "}\n\n";

        $css .= "main footer {\n";
        $css .= "    display: block;\n";
        $css .= "    position: absolute;\n";
        $css .= "    bottom: 0;\n";
        $css .= "    left: 0;\n";
        $css .= "    width: 100%;\n";
        $css .= "    height: var(--footer-height);\n";
        $css .= "    z-index: 1;\n";
        $css .= "    box-sizing: border-box;\n";
        $css .= $this->cssEspaciadoDeclaraciones('footer');
        $css .= "}\n\n";

        $css .= "main ul > li::before {\n";
        $css .= "    content: var(--list-style-bullet);\n";
        $css .= "    display: inline-block;\n";
        $css .= "    width: 1em;\n";
        $css .= "    margin-left: -1em !important;\n";
        $css .= "}\n";
        $css .= "main ul ul > li::before { content: var(--list-style-sub-bullet); }\n";
        $css .= "main ul.checkbox > li::before { content: var(--list-style-checkbox); }\n";
        $css .= "main ul.radio > li::before { content: var(--list-style-radio); }\n\n";
        $css .= "main ol {\n";
        $css .= "    list-style-type: var(--list-style-ordered) !important;\n";
        $css .= "}\n";
        $css .= "main ol ol {\n";
        $css .= "    list-style-type: var(--list-style-ordered-sub) !important;\n";
        $css .= "}\n\n";

        $css .= "main footer::before {\n";
        $css .= "    content: ''; display: block; position: absolute; top: 0; left: 0; width: 100%; height: 100%;\n";
        $css .= "    background-image: var(--footer-imagen); background-size: cover; background-repeat: no-repeat; background-position: center bottom; z-index: -1;\n";
        $css .= "}\n";
        $css .= "main > div:nth-child(odd) footer::before {\n";
        $css .= "    transform: scaleX(-1);\n";
        $css .= "}\n";
        $css .= "main footer::after {\n";
        $css .= "    content: counter(page); position: absolute; bottom: var(--footer-page-y); width: var(--footer-page-x);\n";
        $css .= "}\n";
        $css .= "main > div:nth-child(odd) footer { text-align: left; }\n";
        $css .= "main > div:nth-child(odd) footer::after { right: 0; left: auto; }\n";
        $css .= "main > div:nth-child(even) footer { text-align: right; }\n";
        $css .= "main > div:nth-child(even) footer::after { left: 0; right: auto; }\n\n";

        $css .= "main table {\n";
        foreach (self::MENU_LADOS as $lado) {
            $css .= '    margin-' . $lado . ': calc(var(--table-margin-' . $lado . ', 0px) + (var(--table-gap, 0px) / 2));' . "\n";
        }
        $css .= "    border-collapse: collapse;\n";
        $css .= "}\n\n";
        $css .= "main table:has(th) thead tr th, main table:has(th) tr:has(th) th {\n";
        $css .= "    border-bottom: var(--table-th-border-bottom-width) solid var(--table-th-border-bottom-color);\n";
        $css .= "}\n";
        $css .= "main table:has(th) tr td:first-child, main table:has(th) tr th:first-child {\n";
        $css .= "    border-right: var(--table-col1-border-right-width) solid var(--table-col1-border-right-color);\n";
        $css .= "}\n";
        $css .= "main table:has(th:nth-child(2)) tbody tr:not(:has(td:nth-child(2))) td:first-child { border-right: none; }\n";
        $css .= "main table:has(th:nth-child(3)) tbody tr:not(:has(td:nth-child(3))) td:first-child { border-right: none; }\n";
        $css .= "main table:has(th:nth-child(4)) tbody tr:not(:has(td:nth-child(4))) td:first-child { border-right: none; }\n";
        $css .= "main table:has(th:nth-child(5)) tbody tr:not(:has(td:nth-child(5))) td:first-child { border-right: none; }\n";
        $css .= "main table:has(th:nth-child(6)) tbody tr:not(:has(td:nth-child(6))) td:first-child { border-right: none; }\n";
        $css .= "main table tfoot td, main table tfoot th {\n";
        $css .= "    border-top: 1px solid var(--table-th-border-bottom-color);\n";
        $css .= "}\n";
        $css .= "main table tfoot tr td:first-child, main table tfoot tr th:first-child {\n";
        $css .= "    border-right: none;\n";
        $css .= "}\n";
        $css .= "main table tbody tr:nth-child(even) {\n";
        $css .= "    background-color: var(--table-zebra-bg);\n";
        $css .= "}\n";
        $css .= "main table td, main table th {\n";
        $css .= "    padding: var(--table-td-padding-y) var(--table-td-padding-x);\n";
        $css .= "}\n\n";
        $css .= "main blockquote {\n";
        $css .= "    border-left: var(--blockquote-border-left-width) solid var(--blockquote-border-left-color);\n";
        $css .= "}\n";
        $css .= "main a, main em, main strong {\n";
        $css .= "    color: var(--a-color);\n";
        $css .= "}\n\n";

        $dropcap_size = $variables['--dropcap-size'] ?? '0px';
        if ($dropcap_size !== '0px' && $dropcap_size !== '0') {
            $css .= "main :is(h1 + p, :is(header, section, div:not(.plantilla > div)):has(h1):not(:has(p)) + p, :is(header, section, div:not(.plantilla > div)):has(h1):not(:has(p)) + :is(section, div:not(.plantilla > div)) > p:first-child, .big-letter)::first-letter {\n";
            $css .= "    float: left;\n";
            $css .= "    font-family: var(--dropcap-family);\n";
            $css .= "    font-size: var(--dropcap-size);\n";
            $css .= "    line-height: 1;\n";
            $css .= "    margin-top: calc(var(--dropcap-margin-top) - 10px);\n";
            $css .= "    margin-right: var(--dropcap-margin-right);\n";
            $css .= "    margin-bottom: calc(var(--dropcap-margin-bottom) - 10px);\n";
            $css .= "    margin-left: var(--dropcap-margin-left);\n";
            $css .= "    padding: 10px 0;\n";
            $css .= "    background-image: linear-gradient(to bottom, var(--dropcap-color), color-mix(in srgb, var(--dropcap-color) 40%, transparent));\n";
            $css .= "    background-clip: text;\n";
            $css .= "    -webkit-background-clip: text;\n";
            $css .= "    color: transparent;\n";
            $css .= "    -webkit-text-fill-color: transparent;\n";
            $css .= "}\n\n";
        }

        return $css;
    }

    private function cssEspaciado($selector, $prefix, $extra_declarations = '')
    {
        return $selector . " {\n" . $this->cssEspaciadoDeclaraciones($prefix) . $extra_declarations . "}\n\n";
    }

    private function cssEspaciadoDeclaraciones($prefix)
    {
        $css = '';
        foreach (self::MENU_LADOS as $lado) {
            $css .= '    padding-' . $lado . ': var(--' . $prefix . '-padding-' . $lado . ");\n";
        }
        foreach (self::MENU_LADOS as $lado) {
            $css .= '    margin-' . $lado . ': var(--' . $prefix . '-margin-' . $lado . ");\n";
        }
        return $css;
    }

    /**
     * Auxiliar para generar el CSS de las fuentes (@font-face o @import).
     */
    private function generarCssFuentes($fuentes)
    {
        $css = "";
        $declaradas = [];
        foreach ($fuentes as $f) {
            $f = trim($f, "'\" ");
            if (!$f) continue;

            $f_norm = self::normalizarNombreFuente($f);
            if (!$f_norm || self::esKeywordFuente($f_norm)) continue;

            $clave = $f_norm;
            if (isset($declaradas[$clave])) continue;
            $declaradas[$clave] = true;

            $local = self::buscarFuenteLocal($f_norm);

            $familia = str_replace("'", "\\'", $f_norm);
            if ($local) {
                $css .= "@font-face { font-family: '{$familia}'; src: url('{$local['url']}') format('woff2'); font-display: swap; }\n";
            } else {
                $css .= "@import url('https://fonts.googleapis.com/css2?family=" . str_replace(' ', '+', $f_norm) . "&display=swap');\n";
            }
        }
        return $css;
    }

    /**
     * Normaliza nombres de fuentes para archivos.
     */
    public static function normalizarNombreFuente($nombre) {
        $nombre = str_replace(['+', '%20'], ' ', $nombre);
        $nombre = preg_replace('/[^a-zA-Z0-9 ]/', '', $nombre);
        return trim(preg_replace('/\s+/', ' ', $nombre));
    }

    private static function esKeywordFuente($nombre)
    {
        $keywords = ['inherit', 'initial', 'unset', 'revert', 'serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui', 'default', 'none'];
        return in_array(strtolower(trim($nombre, "'\" ")), $keywords, true);
    }

    public static function slugFuente($nombre)
    {
        $nombre = self::normalizarNombreFuente($nombre);
        return strtolower(str_replace(' ', '_', $nombre));
    }

    private static function rutaBaseFonts()
    {
        $dir_base = $_SERVER['DOCUMENT_ROOT'] ?? '';
        if (!$dir_base) $dir_base = defined('PUB_PATH') ? PUB_PATH : '.';
        return rtrim($dir_base, '\\/') . DIRECTORY_SEPARATOR . 'fonts';
    }

    public static function buscarFuenteLocal($nombre)
    {
        $slug = self::slugFuente($nombre);
        if (!$slug) return null;

        $base = self::rutaBaseFonts();
        $candidatas = [
            "{$slug}/regular.woff2",
            "{$slug}/variable.woff2",
            "{$slug}/{$slug}.woff2",
            "{$slug}.woff2",
            "{$slug}_regular.woff2"
        ];

        foreach ($candidatas as $rel) {
            $abs = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (is_file($abs)) {
                if (self::fuenteLocalValida($abs)) {
                    return [
                        'path' => $abs,
                        'url' => '/fonts/' . str_replace('\\', '/', $rel)
                    ];
                } else {
                    self::borrarFuenteCorrupta($abs);
                }
            }
        }

        foreach (self::fuentesLocales() as $fuente) {
            if (self::slugFuente($fuente['nombre']) === $slug) {
                return [
                    'path' => $fuente['path'],
                    'url' => $fuente['url']
                ];
            }
        }

        return null;
    }

    public static function fuentesLocales()
    {
        $base = self::rutaBaseFonts();
        if (!is_dir($base)) return [];

        $fuentes = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file_item) {
            if (!$file_item->isFile() || strtolower($file_item->getExtension()) !== 'woff2') continue;

            $abs = $file_item->getPathname();
            if (strpos($abs, DIRECTORY_SEPARATOR . '__OLD__' . DIRECTORY_SEPARATOR) !== false) continue;
            if (!self::fuenteLocalValida($abs)) {
                self::borrarFuenteCorrupta($abs);
                continue;
            }

            $rel = ltrim(substr($abs, strlen($base)), '\/');
            $rel_url = str_replace('\\', '/', $rel);
            $filename = strtolower($file_item->getBasename('.woff2'));
            $parent = basename($file_item->getPath());
            $slug = (in_array($filename, ['regular', 'variable'], true) && $parent !== 'fonts') ? $parent : $filename;
            if (str_ends_with($slug, '_regular')) $slug = substr($slug, 0, -8);

            $nombre = ucwords(str_replace('_', ' ', $slug));
            $priority = self::prioridadFuenteLocal($filename, $parent);
            $key = strtolower($nombre);
            if (isset($fuentes[$key]) && $fuentes[$key]['priority'] >= $priority) continue;

            $fuentes[$key] = [
                'nombre' => $nombre,
                'path' => $abs,
                'url' => '/fonts/' . $rel_url,
                'priority' => $priority
            ];
        }

        uasort($fuentes, function ($a, $b) {
            return strcasecmp($a['nombre'], $b['nombre']);
        });

        return array_values($fuentes);
    }

    private static function descargarUrl($url, $headers = '')
    {
        $contexto = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 20,
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n" . $headers
            ]
        ]);
        return @file_get_contents($url, false, $contexto);
    }

    private static function fuenteLocalValida($path)
    {
        if (!is_file($path) || filesize($path) < 1024) return false;
        $fh = @fopen($path, 'rb');
        if (!$fh) return false;
        $magic = fread($fh, 4);
        if ($magic !== 'wOF2') {
            fclose($fh);
            return false;
        }
        // Skip flavor (4 bytes)
        fread($fh, 4);
        // Read length (4 bytes)
        $len_bytes = fread($fh, 4);
        fclose($fh);

        $unpacked = unpack('Nlength', $len_bytes);
        $header_length = $unpacked['length'];

        return (filesize($path) === $header_length);
    }

    public static function borrarFuenteCorrupta($path)
    {
        if (is_file($path)) {
            @unlink($path);
            self::resetearEstadoDescargaPorRuta($path);
        }
    }

    public static function resetearEstadoDescargaPorRuta($path)
    {
        $filename = strtolower(pathinfo($path, PATHINFO_FILENAME));
        $parent = basename(dirname($path));
        $slug = (in_array($filename, ['regular', 'variable'], true) && $parent !== 'fonts') ? $parent : $filename;
        if (str_ends_with($slug, '_regular')) $slug = substr($slug, 0, -8);

        try {
            $catalogo = new Plantillas_fuentes_catalogo();
            $catalogo->query("CREATE TABLE IF NOT EXISTS plantillas_fuentes_catalogo (
                id_fuente INT AUTO_INCREMENT PRIMARY KEY,
                nombre_fuente VARCHAR(255) NOT NULL,
                descargada_local TINYINT(1) DEFAULT 0,
                UNIQUE KEY (nombre_fuente)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $fuentes = $catalogo->all("SELECT id_fuente, nombre_fuente FROM plantillas_fuentes_catalogo WHERE descargada_local=1");
            foreach ($fuentes as $f) {
                if (self::slugFuente($f->nombre_fuente) === $slug) {
                    $catalogo->query("UPDATE plantillas_fuentes_catalogo SET descargada_local=0 WHERE id_fuente=?", [$f->id_fuente]);
                }
            }
        } catch (Exception $e) {
        }
    }

    private static function prioridadFuenteLocal($filename, $parent)
    {
        if ($parent !== 'fonts' && $filename === 'regular') return 100;
        if ($parent !== 'fonts' && $filename === 'variable') return 90;
        if (str_ends_with($filename, '_regular')) return 80;
        return 70;
    }

    private static function urlsWoff2Google($css)
    {
        $candidatas = [];
        preg_match_all('/@font-face\s*{.*?}/is', $css, $blocks);
        foreach ($blocks[0] ?? [] as $block) {
            if (!preg_match('/url\((https:\/\/[^)]+?\.woff2(?:\?[^)]*)?)\)/i', $block, $m)) continue;
            $weight = preg_match('/font-weight:\s*700/i', $block) ? 700 : 400;
            $latin = stripos($block, 'U+0000-00FF') !== false ? 1 : 0;
            $candidatas[] = [
                'url' => $m[1],
                'score' => ($latin * 100) + ($weight === 400 ? 10 : 0)
            ];
        }

        if (!$candidatas) {
            preg_match_all('/url\((https:\/\/[^)]+?\.woff2(?:\?[^)]*)?)\)/i', $css, $matches);
            foreach (array_unique($matches[1] ?? []) as $url) {
                $candidatas[] = ['url' => $url, 'score' => 0];
            }
        }

        usort($candidatas, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_values(array_unique(array_column($candidatas, 'url')));
    }

    /**
     * Sube una imagen y retorna su URL.
     */
    private function subirImagen($key, $prefijo)
    {
        if (!isset($_FILES[$key]) || !$_FILES[$key]['tmp_name']) {
            if (isset($_FILES[$key]) && $_FILES[$key]['error'] !== UPLOAD_ERR_OK && $_FILES[$key]['error'] !== UPLOAD_ERR_NO_FILE) {
                $errores = [
                    UPLOAD_ERR_INI_SIZE => "El archivo excede upload_max_filesize.",
                    UPLOAD_ERR_FORM_SIZE => "El archivo excede MAX_FILE_SIZE.",
                    UPLOAD_ERR_PARTIAL => "Subida parcial.",
                    UPLOAD_ERR_NO_TMP_DIR => "Falta carpeta temporal.",
                    UPLOAD_ERR_CANT_WRITE => "Error al escribir en disco.",
                    UPLOAD_ERR_EXTENSION => "Extensión bloqueada."
                ];
                Session::setArray('toast', "Error PHP: " . ($errores[$_FILES[$key]['error']] ?? "Desconocido"));
            }
            return null;
        }
        
        $ext = pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION);
        $nombre_base = pathinfo($_FILES[$key]['name'], PATHINFO_FILENAME);
        $nombre = _str::uid($prefijo) . "_" . _url::slug($nombre_base) . "." . strtolower($ext);
        
        $idu_folder = Session::get('idu') ?: 'public';
        $ruta_relativa = "img/usuarios/$idu_folder/$nombre";
        
        // Priorizamos DOCUMENT_ROOT para evitar problemas con PUB_PATHs mal configurados en remoto
        $dir_base = $_SERVER['DOCUMENT_ROOT'];
        // Si no hay DOCUMENT_ROOT (CLI), usamos PUB_PATH o el actual
        if (!$dir_base) $dir_base = defined('PUB_PATH') ? PUB_PATH : '.';

        $ruta_absoluta = rtrim($dir_base, '\\/') . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $ruta_relativa), '\\/');
        
        $folder = dirname($ruta_absoluta);
        if (!is_dir($folder)) {
            if (!@mkdir($folder, 0775, true)) {
                Session::setArray('toast', "Error: No se pudo crear la carpeta de destino. Verifique permisos en img/usuarios/");
                return null;
            }
        }
        
        try {
            $result = MediaProcessor::processUpload($_FILES[$key], $folder, [
                'basename' => pathinfo($nombre, PATHINFO_FILENAME),
                // CSS backgrounds cannot display MP4; animated WebP keeps motion.
                'animated_gif' => 'webp',
            ]);
            $served = $result['variants']['l'] ?? $result['name'];
            return "/img/usuarios/$idu_folder/$served";
        } catch (Throwable $e) {
            error_log('Template media upload failed: ' . $e->getMessage());
            Session::setArray('toast', 'No se pudo procesar la imagen subida.');
            return null;
        }
        
        Session::setArray('toast', "Error interno: No se pudo mover el archivo. Verifique permisos de escritura en " . basename($folder));
        return null;
    }

    public static function normalizarNombreGoogleFonts($nombre)
    {
        $mapping = [
            'eb garamond' => 'EB Garamond',
            'alegreya sc' => 'Alegreya SC',
            'cormorant sc' => 'Cormorant SC',
            'cormorant unicase' => 'Cormorant Unicase',
            'exo 2' => 'Exo 2'
        ];
        $lower = strtolower(trim($nombre));
        if (isset($mapping[$lower])) {
            return $mapping[$lower];
        }
        return ucwords($lower);
    }

    /**
     * Descarga una fuente de Google Fonts localmente y la registra en el catálogo.
     */
    public static function descargarFuente($nombre, $forzar = false)
    {
        $nombre = self::normalizarNombreFuente($nombre);
        if (!$nombre) {
            return false;
        }

        if (self::esKeywordFuente($nombre)) {
            return true;
        }

        if (!$forzar && self::buscarFuenteLocal($nombre)) {
            return true;
        }

        try {
            // 1. Asegurar que la tabla exista
            $catalogo = new Plantillas_fuentes_catalogo();
            $catalogo->query("CREATE TABLE IF NOT EXISTS plantillas_fuentes_catalogo (
                id_fuente INT AUTO_INCREMENT PRIMARY KEY,
                nombre_fuente VARCHAR(255) NOT NULL,
                descargada_local TINYINT(1) DEFAULT 0,
                UNIQUE KEY (nombre_fuente)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            // 2. Comprobar si ya se descargó
            $fuente = $catalogo->first("SELECT * FROM plantillas_fuentes_catalogo WHERE nombre_fuente=?", [$nombre]);
            if (!$forzar && $fuente && $fuente->descargada_local && self::buscarFuenteLocal($nombre)) {
                return true;
            }

            if (!$fuente) {
                $catalogo->query("INSERT INTO plantillas_fuentes_catalogo SET nombre_fuente=?, descargada_local=0", [$nombre]);
            }

            // 3. Descargar CSS de Google
            $nombre_google = self::normalizarNombreGoogleFonts($nombre);
            $url_css = 'https://fonts.googleapis.com/css2?family=' . str_replace(' ', '+', $nombre_google) . ':wght@400;700&display=swap';
            $css = self::descargarUrl($url_css, "Accept: text/css,*/*;q=0.1\r\n");
            
            if ($css) {
                $urls = self::urlsWoff2Google($css);

                foreach ($urls as $url_woff2) {
                    $woff2_content = self::descargarUrl($url_woff2);
                    if ($woff2_content) {
                        $slug = self::slugFuente($nombre);
                        $ruta = self::rutaBaseFonts() . DIRECTORY_SEPARATOR;

                        if (!is_dir($ruta)) {
                            mkdir($ruta, 0775, true);
                        }
                        $archivo_destino = $ruta . $slug . '.woff2';
                        file_put_contents($archivo_destino, $woff2_content);

                        if (self::fuenteLocalValida($archivo_destino)) {
                            $catalogo->query("UPDATE plantillas_fuentes_catalogo SET descargada_local=1 WHERE nombre_fuente=?", [$nombre]);
                            return true;
                        } else {
                            self::borrarFuenteCorrupta($archivo_destino);
                        }
                    }
                }
            }
        } catch (Exception $e) {
        }
        return false;
    }

    /**
     * Retorna la URL del CSS compilado.
     */
    public function getCssUrl()
    {
        $dir_base = $_SERVER['DOCUMENT_ROOT'] ?: (defined('PUB_PATH') ? PUB_PATH : '.');
        $dir_base = rtrim($dir_base, '\\/');
        $base_rel = "css/plantillas/{$this->idu}";
        
        $min_abs = $dir_base . DIRECTORY_SEPARATOR . $base_rel . ".min.css";
        $css_abs = $dir_base . DIRECTORY_SEPARATOR . $base_rel . ".css";

        if (file_exists($min_abs)) return "/$base_rel.min.css?t=" . filemtime($min_abs);
        if (file_exists($css_abs)) return "/$base_rel.css?t=" . filemtime($css_abs);
        return "";
    }

    /**
     * FONTANERÍA INTERNA
     */
    const NIVELES_TIPOGRAFIA = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'body', 'small', 'dropcap'];

    const SELECTORES_TIPOGRAFIA = [
        'h1'    => 'main.plantilla h1',
        'h2'    => 'main.plantilla h2',
        'h3'    => 'main.plantilla h3',
        'h4'    => 'main.plantilla h4',
        'h5'    => 'main.plantilla h5',
        'h6'    => 'main.plantilla h6',
        'body'  => 'main.plantilla',
        'small' => 'main.plantilla small'
    ];
}
