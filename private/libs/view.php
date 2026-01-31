<?php
/**
 * @see KumbiaView
 */
require_once CORE_PATH . 'kumbia/kumbia_view.php';

/**
 * Esta clase permite extender o modificar la clase ViewBase de Kumbiaphp.
 *
 * @category KumbiaPHP
 * @package View
 */
class View extends KumbiaView
{

}

function t($traducir)
{
    $idioma = $_COOKIE['usuario']['idioma'] ?? 'ES';
        
    if ($idioma === 'ES') {
        return $traducir;
    }

    static $archivo;
    if ( ! isset($archivo[$idioma])) {
        $archivo[$idioma] = require APP_PATH . "config/idiomas_$idioma.php";
    }

    return $archivo[$idioma][$traducir] ?? $traducir;
}
