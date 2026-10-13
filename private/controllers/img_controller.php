<?php
/** Imágenes públicas: mismo acceso que los ficheros estáticos, sin contexto de página. */
class ImgController extends GeneralController
{
    protected function initialize()
    {
        View::select(null, null);
        Session::close();
    }

    public function __call($action_name, $params)
    {
        if ( ! preg_match('/\A[a-z0-9_-]+\z/i', $action_name) || empty($params[0]) || count($params) > 2) {
            return $this->notFound();
        }
        foreach ($params as $part) {
            if ($part === '.' || $part === '..' || strpbrk($part, "/\\\0") !== false) {
                return $this->notFound();
            }
        }
        $nombre = count($params) === 1 ? $params[0] : $params[1];
        if ( ! preg_match('/\A(xxs|xs|s|m|l|xl|xxl)\.(.+)\z/is', $nombre, $partes)) {
            return $this->notFound();
        }
        $miniatura = strtolower($partes[1]);
        $usuario = count($params) === 1 ? '' : '/' . $params[0];
        $original = "img/$action_name$usuario/" . trim($partes[2]);
        $root = realpath('img');
        $real = realpath($original);
        if ($root === false || $real === false || ! is_file($real) || ! is_readable($real)
            || strpos($real, $root . DIRECTORY_SEPARATOR) !== 0) {
            return $this->notFound();
        }
        $anchos = Config::get('vistas.miniaturas');
        if (empty($anchos[$miniatura])) {
            return $this->notFound();
        }
        Redirect::to(Thumbnail::make($original, $miniatura));
    }

    private function notFound()
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: private, max-age=15');
        echo 'Imagen no encontrada.';
    }
}
