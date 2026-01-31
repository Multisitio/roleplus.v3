<?php
require_once CORE_PATH . 'kumbia/controller.php';

/**
 * Controlador padre intermedio para filtrar contenido JS malicioso
 * permitiendo HTML y CSS.
 */
class GeneralController extends Controller
{
    /**
     * Limpia recursivamente arrays de inputs ($_GET, $_POST) de vectores XSS comunes.
     * Permite HTML y CSS, pero elimina <script>, eventos on*, y javascript:.
     */
    /**
     * Limpia recursivamente arrays de inputs ($_GET, $_POST) usando Expresiones Regulares.
     * Mantiene el HTML intacto (no añade html/body) y elimina JS, eventos y protocolos peligrosos.
     */
    protected function _filtrar_js_recursivo(&$input)
    {
        if (is_array($input)) {
            foreach ($input as $key => &$value) {
                $this->_filtrar_js_recursivo($value);
            }
        } elseif (is_string($input)) {
            // 1. Eliminar etiquetas <script> y su contenido
            // Modificadores: i (case insensitive), s (dot matches newline)
            $input = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $input);

            // 2. Eliminar atributos de eventos on* (ej: onclick="...", onload='...')
            // Busca: espacio + on[letras] + = + comillas + contenido + comillas
            $input = preg_replace('/\s+on[a-z]+\s*=\s*[\'"][^\'"]*[\'"]/i', "", $input);

            // 3. Eliminar pseudo-protocolo javascript: en href o src
            // Busca: href/src/data/action + = + comillas + javascript: + resto
            $input = preg_replace('/(href|src|data|action|formaction)\s*=\s*[\'"]\s*javascript:[^\'"]*[\'"]/i', '$1="#"', $input);
        }
    }
}
