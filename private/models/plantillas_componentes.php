<?php
/** Reglas decorativas de BD. Los ajustes comunes conservan su contrato actual. */
class Plantillas_componentes extends LiteRecord
{
    /** Native variables from the three migrated file templates. */
    const VARIABLES_ANTERIORES = [
        '--bg-band',
        '--bg-blockquote',
        '--bg-footer',
        '--bg-page',
        '--bg-table-stripe',
        '--border-card',
        '--border-h3',
        '--border-image-blockquote',
        '--border-td',
        '--border-th',
        '--card-height',
        '--card-min-width',
        '--card-title-height',
        '--card-title-width',
        '--color-accent',
        '--color-accent-gold',
        '--color-band-bg',
        '--color-band-shadow',
        '--color-bg-accent',
        '--color-bg-cover',
        '--color-bg-even-row',
        '--color-blockquote-bg',
        '--color-blockquote-shadow',
        '--color-border-primary',
        '--color-card-text',
        '--color-emphasis',
        '--color-primary',
        '--color-secondary',
        '--color-tertiary',
        '--color-text',
        '--color-text-emphasis',
        '--color-text-footnote',
        '--color-text-header',
        '--color-text-inverse',
        '--column-gap',
        '--demongreen',
        '--dragonred',
        '--fleshbrown',
        '--font-annotation',
        '--font-body',
        '--font-decorative',
        '--font-handwritten',
        '--font-heading',
        '--font-initial',
        '--font-size-body',
        '--font-size-caption',
        '--font-size-card-title',
        '--font-size-drop-cap',
        '--font-size-footer',
        '--font-size-footer-num',
        '--font-size-h1',
        '--font-size-h2',
        '--font-size-h3',
        '--font-size-h4',
        '--font-size-h5',
        '--font-size-h6',
        '--font-size-mark',
        '--font-table',
        '--font-title',
        '--footer-counter-width',
        '--gradient-forest',
        '--hb_color_accent',
        '--hb_color_background',
        '--hb_color_captiontext',
        '--hb_color_footnotes',
        '--hb_color_headertext',
        '--hb_color_headerunderline',
        '--hb_color_horizontalrule',
        '--hb_color_monsterstatbackground',
        '--hb_color_watercolorstain',
        '--img-watermark-opacity',
        '--leatherbrown',
        '--link',
        '--list-bullet',
        '--list-checkbox',
        '--list-padding',
        '--list-radio',
        '--list-sub-bullet',
        '--mark-size',
        '--parchmentyellow',
        '--shadow-blockquote',
        '--shadow-text-glow',
        '--shadow-text-light',
        '--spacing-after-heading',
        '--spacing-block-heading',
        '--spacing-block-list',
        '--spacing-blockquote-block',
        '--spacing-cell',
        '--spacing-heading-bottom',
        '--spacing-li',
        '--spacing-page-bottom',
        '--spacing-page-inline',
        '--spacing-page-top',
    ];

    public function variablesAnteriores($idu)
    {
        $rows = self::all('SELECT * FROM plantillas_reglas WHERE plantillas_idu=? AND selector=? ORDER BY propiedad, id', [$idu, Plantillas_reglas::SELECTOR_VARIABLES]);
        return array_filter($rows, fn($r) => in_array($r->propiedad, self::VARIABLES_ANTERIORES, true));
    }

    public function reglas($idu)
    {
        return self::all('SELECT * FROM plantillas_reglas WHERE plantillas_idu=? AND selector != ? ORDER BY peso, id', [$idu, Plantillas_reglas::SELECTOR_VARIABLES]);
    }

    public function css($idu, array $variables, $fuentes = '')
    {
        $css = $fuentes;
        $selector = null;
        $peso = null;
        foreach ($this->reglas($idu) as $r) {
            if ($selector !== $r->selector || $peso !== $r->peso) {
                if ($selector !== null) {
                    $css .= "}\n";
                }
                $selector = $r->selector;
                $peso = $r->peso;
                $css .= $selector . " {\n";
            }
            $css .= '  ' . $r->propiedad . ': ' . $r->valor . ";\n";
        }
        if ($selector !== null) {
            $css .= "}\n";
        }
        $css .= "main {\n";
        foreach ($variables as $propiedad => $valor) {
            if ( ! str_starts_with($propiedad, '--componentes-')) {
                $css .= '  ' . $propiedad . ': ' . $valor . ";\n";
            }
        }
        $css .= "}\n";
        $css .= "main > div > article {background-color: var(--background-color, white); -webkit-print-color-adjust: exact; print-color-adjust: exact;}\n";
        $css .= "main > div > a.button-floating {position: absolute; top: -15px; right: -15px;}\n";
        $css .= $this->ajustesModificados($variables);
        // Named pages replace the inline @page declarations for these templates.
        foreach (['a4' => 'A4', 'a5' => 'A5', 'letter' => 'Letter'] as $clase => $tamano) {
            foreach (['portrait', 'landscape'] as $orientacion) {
                $nombre = $clase . $orientacion;
                $css .= "@page {$nombre} {size: {$tamano} {$orientacion}; margin: 0;}\n";
                $css .= "main.{$clase}.{$orientacion} {page: {$nombre};}\n";
            }
        }
        return $css;
    }

    private function cambiado(array $vars, $key)
    {
        return isset($vars[$key], $vars['--componentes-base-' . substr($key, 2)])
            && $vars[$key] !== $vars['--componentes-base-' . substr($key, 2)];
    }

    private function ajustesModificados(array $vars)
    {
        $css = '';
        $tipografia = ['family' => 'font-family', 'size' => 'font-size', 'color' => 'color', 'align' => 'text-align',
            'weight' => 'font-weight', 'style' => 'font-style', 'transform' => 'text-transform', 'variant' => 'font-variant', 'margin-top' => 'margin-top'];
        foreach (Plantillas::SELECTORES_TIPOGRAFIA + ['dropcap' => 'main .big-letter::first-letter'] as $nivel => $selector) {
            if ($nivel === 'dropcap') {
                $selector = $vars['--componentes-capitular-selector'] ?? $selector;
            }
            if ($nivel === 'body') {
                $selector = 'main > div > article';
            }
            foreach ($tipografia as $suffix => $propiedad) {
                $key = '--' . $nivel . '-' . $suffix;
                if ($this->cambiado($vars, $key)) {
                    if ($nivel === 'dropcap' && $suffix === 'color') {
                        $css .= "{$selector} {background-image: linear-gradient(to bottom, var(--dropcap-color), color-mix(in srgb, var(--dropcap-color) 40%, transparent)) !important;}\n";
                    } else {
                        $css .= "{$selector} { {$propiedad}: var({$key}) !important; }\n";
                    }
                }
            }
            $key = '--' . $nivel . '-decoration';
            if (preg_match('/^h[1-6]$/', $nivel)) {
                // The heading rule remains editable even when returning to its base value.
                $grosor = ($vars[$key] ?? '0') === 'none' ? '0' : "var({$key}, 0)";
                $css .= "{$selector} {text-decoration: none !important; border-bottom: {$grosor} solid currentColor !important;}\n";
            } elseif ($this->cambiado($vars, $key)) {
                $linea = in_array($vars[$key], ['0', 'none'], true) ? 'none' : 'underline';
                $css .= "{$selector} {text-decoration: {$linea} !important;}\n";
            }
            if ($nivel === 'dropcap') {
                foreach (['right', 'bottom', 'left'] as $lado) {
                    $key = '--dropcap-margin-' . $lado;
                    if ($this->cambiado($vars, $key)) {
                        $css .= "{$selector} {margin-{$lado}: var({$key}) !important;}\n";
                    }
                }
            }
        }
        foreach (['header', 'section', 'footer', 'p', 'blockquote', 'list', 'table'] as $bloque) {
            $selector = $bloque === 'list' ? 'main :is(ul, ol)' : 'main ' . $bloque;
            foreach (['margin', 'padding'] as $propiedad) {
                foreach (['top', 'right', 'bottom', 'left'] as $lado) {
                    $key = '--' . $bloque . '-' . $propiedad . '-' . $lado;
                    if ($bloque !== 'footer' && $propiedad === 'margin' && $lado === 'top') {
                        continue; // Block separation belongs exclusively to the block gap.
                    }
                    if ($this->cambiado($vars, $key)) {
                        $valor = "var({$key})";
                        $css .= "{$selector} { {$propiedad}-{$lado}: {$valor} !important; }\n";
                    }
                }
            }
        }
        $reglas = [
            '--background-image' => ['main > div:nth-child(odd) article', 'background-image'],
            '--background-color' => ['main > div:nth-child(odd) article', 'background-color'],
            '--background-color-even' => ['main > div:nth-child(even) article', 'background-color'],
            '--a-color' => ['main :is(a, em, strong)', 'color'],
            '--footer-height' => ['main footer', 'height'],
            '--footer-page-x' => ['main footer::after', 'width'],
            '--footer-page-y' => ['main footer::after', 'bottom'],
            '--list-style-bullet' => ['main ul > li::before', 'content'],
            '--list-style-sub-bullet' => ['main ul ul > li::before', 'content'],
            '--list-style-checkbox' => ['main ul.checkbox > li::before', 'content'],
            '--list-style-radio' => ['main ul.radio > li::before', 'content'],
            '--list-style-ordered' => ['main ol', 'list-style-type'],
            '--list-style-ordered-sub' => ['main ol ol', 'list-style-type'],
            '--blockquote-border-left-color' => ['main blockquote', 'border-left-color'],
            '--blockquote-border-left-width' => ['main blockquote', 'border-left-width'],
            '--table-th-border-bottom-color' => ['main th', 'border-bottom-color'],
            '--table-th-border-bottom-width' => ['main th', 'border-bottom-width'],
            '--table-col1-border-right-color' => ['main :is(td, th):first-child', 'border-right-color'],
            '--table-col1-border-right-width' => ['main :is(td, th):first-child', 'border-right-width'],
        ];
        foreach ($reglas as $key => [$selector, $propiedad]) {
            if ($this->cambiado($vars, $key)) {
                $css .= "{$selector} { {$propiedad}: var({$key}) !important; }\n";
            }
        }
        $fondo_par = trim($vars['--background-image-even'] ?? 'none');
        $hereda_fondo = $fondo_par === '' || strtolower($fondo_par) === 'none';
        if ($this->cambiado($vars, '--background-image-even')
            || ($hereda_fondo && $this->cambiado($vars, '--background-image'))) {
            $variable_fondo = $hereda_fondo ? '--background-image' : '--background-image-even';
            $css .= "main > div:nth-child(even) article {background-image: var({$variable_fondo}) !important;}\n";
        }
        if ($this->cambiado($vars, '--footer-imagen')) {
            $css .= "main footer::before {content: ''; position: absolute; inset: 0; background-image: var(--footer-imagen); background-repeat: no-repeat; background-position: center bottom; z-index: -1;}\n";
        }
        if ($this->cambiado($vars, '--table-zebra-color') || $this->cambiado($vars, '--table-zebra-opacity')) {
            $css .= "main tbody tr:nth-child(even) {background-color: color-mix(in srgb, var(--table-zebra-color) calc(var(--table-zebra-opacity) * 1%), transparent) !important;}\n";
        }
        foreach (['x' => ['left', 'right'], 'y' => ['top', 'bottom']] as $eje => $lados) {
            $key = '--table-td-padding-' . $eje;
            if (isset($vars[$key])) {
                foreach ($lados as $lado) {
                    $css .= "main :is(td, th) {padding-{$lado}: var({$key}) !important;}\n";
                }
            }
        }
        return $css;
    }

    public function eliminarRegla($plantilla_idu, $regla_idu)
    {
        $regla = self::first('SELECT idu FROM plantillas_reglas WHERE plantillas_idu=? AND idu=? AND selector != ?',
            [$plantilla_idu, $regla_idu, Plantillas_reglas::SELECTOR_VARIABLES]);
        if ( ! $regla) {
            throw new InvalidArgumentException('Regla no encontrada en esta plantilla.');
        }
        self::query('DELETE FROM plantillas_reglas WHERE plantillas_idu=? AND idu=? AND selector != ?',
            [$plantilla_idu, $regla_idu, Plantillas_reglas::SELECTOR_VARIABLES]);
    }

    public function guardarValor($plantilla_idu, $regla_idu, $valor)
    {
        $regla = self::first('SELECT * FROM plantillas_reglas WHERE plantillas_idu=? AND idu=?', [$plantilla_idu, $regla_idu]);
        if ( ! $regla || ($regla->selector === Plantillas_reglas::SELECTOR_VARIABLES
            && ! in_array($regla->propiedad, self::VARIABLES_ANTERIORES, true))) {
            throw new InvalidArgumentException('Regla no encontrada en esta plantilla.');
        }
        $valor = trim((string)$valor);
        if ( ! $this->valorValido($valor)) {
            throw new InvalidArgumentException('Valor CSS no válido.');
        }
        self::query('UPDATE plantillas_reglas SET valor=? WHERE plantillas_idu=? AND idu=?',
            [$valor, $plantilla_idu, $regla_idu]);
    }

    private function valorValido($valor)
    {
        if ($valor === '' || preg_match('/javascript\s*:|expression\s*\(|@import/i', $valor)) {
            return false;
        }
        $quote = '';
        $depth = 0;
        for ($i = 0, $len = strlen($valor); $i < $len; $i++) {
            $char = $valor[$i];
            if ($char === '\\') {
                $i++;
            } elseif ($quote !== '') {
                if ($char === $quote) {
                    $quote = '';
                }
            } elseif ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth < 0) {
                    return false;
                }
            } elseif (strpos('{};', $char) !== false && $depth === 0) {
                return false;
            }
        }
        return $quote === '' && $depth === 0;
    }
}
