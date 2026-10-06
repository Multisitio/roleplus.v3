<?php
/** Reglas decorativas de BD. Los ajustes comunes conservan su contrato actual. */
class Plantillas_componentes extends LiteRecord
{
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
            if ($this->cambiado($vars, $key)) {
                $linea = in_array($vars[$key], ['0', 'none'], true) ? 'none' : 'underline';
                $css .= "{$selector} {text-decoration: {$linea} !important;}\n";
                if ($nivel === 'h3' && $linea === 'none') {
                    $css .= "{$selector} {border-bottom: none !important;}\n";
                }
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
                    if ($this->cambiado($vars, $key)) {
                        $css .= "{$selector} { {$propiedad}-{$lado}: var({$key}) !important; }\n";
                    }
                }
            }
        }
        $reglas = [
            '--background-image' => ['main > div:nth-child(odd) article', 'background-image'],
            '--background-image-even' => ['main > div:nth-child(even) article', 'background-image'],
            '--background-color' => ['main > div:nth-child(odd) article', 'background-color'],
            '--background-color-even' => ['main > div:nth-child(even) article', 'background-color'],
            '--a-color' => ['main :is(a, em, strong)', 'color'],
            '--p-gap' => ['main p + p', 'margin-top'],
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
        if ($this->cambiado($vars, '--footer-imagen')) {
            $css .= "main footer::before {content: ''; position: absolute; inset: 0; background-image: var(--footer-imagen); background-repeat: no-repeat; background-position: center bottom; z-index: -1;}\n";
        }
        if ($this->cambiado($vars, '--table-zebra-color') || $this->cambiado($vars, '--table-zebra-opacity')) {
            $css .= "main tbody tr:nth-child(even) {background-color: color-mix(in srgb, var(--table-zebra-color) calc(var(--table-zebra-opacity) * 1%), transparent) !important;}\n";
        }
        foreach (['x' => ['left', 'right'], 'y' => ['top', 'bottom']] as $eje => $lados) {
            $key = '--table-td-padding-' . $eje;
            if ($this->cambiado($vars, $key)) {
                foreach ($lados as $lado) {
                    $css .= "main :is(td, th) {padding-{$lado}: var({$key}) !important;}\n";
                }
            }
        }
        return $css;
    }

    public function guardarValor($plantilla_idu, $regla_idu, $valor)
    {
        $valor = trim((string)$valor);
        if ( ! $this->valorValido($valor)) {
            throw new InvalidArgumentException('Valor CSS no válido.');
        }
        self::query('UPDATE plantillas_reglas SET valor=? WHERE plantillas_idu=? AND idu=? AND selector != ?',
            [$valor, $plantilla_idu, $regla_idu, Plantillas_reglas::SELECTOR_VARIABLES]);
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
