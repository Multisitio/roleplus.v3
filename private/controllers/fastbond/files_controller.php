<?php
/**
 */
class FilesController extends FastbondController
{
    # 0
    /*public function __call($name, $params)
    {
        $this->index($name, ...$params);
    }*/

    # 1
    public function index()
    {
        $this->file = $file = $_GET['file'];

        if (strstr($file, '/config')) {
            return $this->config($file);
        }

        $this->content = _fastbond::readFile($file);
    }

    # 2
    public function config($file)
    {
        $this->file = $file;
        $content = file_get_contents(APP_PATH . 'config/'. basename($file));
        $tokens = PhpToken::tokenize($content);

        $array = _fastbond::arrayByTokens($tokens);
        _var::die($array);

        if ( ! is_array($this->content)) {
            $this->content = [];
        }
        View::select('config');
    }
}
