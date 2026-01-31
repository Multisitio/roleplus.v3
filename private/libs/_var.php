<?php
/**
 * NAVAJA SUIZA
 */
class _var
{
    // Descarga el buffer para imprimir variables en ejecución sin cerrar el buffer base
    public static function flush($s = '')
    {
        if ($s) {
            self::echo($s);
        }
        // Si existe al menos un buffer, dejamos el buffer base (nivel 1) activo
        if (ob_get_level() > 0) {
            // Mientras haya más de 1 nivel de buffer, cerramos los adicionales
            while (ob_get_level() > 1) {
                @ob_end_flush();
            }
            // Vaciamos el contenido del buffer base sin cerrarlo
            ob_flush();
        }
        flush();
    }

    /**
     * Extrae la variable $var (por defecto “v”) de una URL.
     * Se encarga de todos los formatos habituales de YouTube:
     *   · https://youtu.be/<id>[?…]
     *   · https://www.youtube.com/watch?v=<id>&…
     *   · https://youtube.com/shorts/<id>
     *   · https://youtube.com/live/<id>?feature=share
     *
     * Si la cadena recibida trae texto antes o después de la URL,
     * primero se queda solo con la 1.ª URL válida.
     *
     * @return string|false  El valor de la variable, o false si no se encuentra.
     */
    public static function getUrlVar($url, $var = 'v')
    {
        /*--- NORMALIZA: aisla la primera URL bien formada -------------*/
        if (preg_match('#https?://[^\s]+#', $url, $m)) {
            $url = $m[0];
        }

        /*--- Formato corto   youtu.be/<id>[?…] ------------------------*/
        if (strpos($url, 'youtu.be/') !== false) {
            $path = parse_url($url, PHP_URL_PATH);   // "/<id>"
            $id   = ltrim($path, '/');               // "<id>"
            $id   = preg_replace('/[^A-Za-z0-9_-].*/', '', $id);
            return $id ?: false;
        }

        /*--- Formato shorts o live  ----------------------------------*/
        if (strpos($url, '/shorts/') !== false || strpos($url, '/live/') !== false) {
            $url      = explode('?', $url, 2)[0];    // corta ?…
            $segments = explode('/', $url);
            return end($segments);
        }

        /*--- URLs con parámetros de consulta -------------------------*/
        $parts = parse_url($url);
        if (empty($parts['query'])) {
            return false;
        }
        parse_str($parts['query'], $query);
        return $query[$var] ?? false;
    }

    // Retorna la variable formateada para impresión
    public static function return($var = '', $no_tags = 0)
    {
        if ($no_tags) {
            if (is_array($var)) {
                array_walk_recursive($var, function (&$value) {
                    if (is_bool($value)) {
                        $value = $value ? 'TRUE' : 'FALSE';
                    } elseif (is_string($value)) {
                        $value = str_replace('<', '&lt;', $value);
                    }
                });
            } else {
                if (is_bool($var)) {
                    $var = $var ? 'TRUE' : 'FALSE';
                } elseif (is_null($var)) {
                    $var = 'NULL';
                } elseif (is_string($var)) {
                    $var = str_replace('<', '&lt;', $var);
                }
            }
        } else {
            if (is_bool($var)) {
                $var = $var ? 'TRUE' : 'FALSE';
            } elseif (is_null($var)) {
                $var = 'NULL';
            }
        }
        return '<pre>' . print_r($var, true) . '</pre>';
    }

    // Imprime la variable formateada
    public static function echo($var = '', $no_tags = 0)
    {
        echo self::return($var, $no_tags);
    }

    // Muestra la variable y finaliza la ejecución (si el IP está autorizado)
    public static function die($var = '', $no_tags = 0)
    {
        $allowed_IP = Config::get('exception.admin');
        if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_IP)) {
            return;
        }
        echo round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 4) . ' ms<br>';
        echo $var ? '<h3>RESULT</h3>' : '<h3>EMPTY</h3>';
        self::echo($var, $no_tags);
        exit;
    }

    // Obtiene el valor de un array u objeto
    public static function val($var, $key)
    {
        if (is_array($var)) {
            return isset($var[$key]) ? $var[$key] : '';
        } elseif (is_object($var)) {
            return isset($var->$key) ? $var->$key : '';
        }
        return '';
    }

    // Retorna el valor de $_POST para una clave dada
    public static function post($key)
    {
        return isset($_POST[$key]) ? $_POST[$key] : '';
    }
}
