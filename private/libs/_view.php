<?php
class _view {
    public static $content = '';
    private static $executed = false;

    public static function partial($name) {
        if (!self::$executed) {
            $file = APP_PATH . "views/_shared/partials/{$name}.phtml";
            if (is_file($file)) {
                require $file;
                // Las variables $content y $title del partial existen ahora aquí
                self::$content = isset($content) ? $content : '';
                self::$executed = true;
                return isset($title) ? $title : '';
            }
        }
        return '';
    }
}
