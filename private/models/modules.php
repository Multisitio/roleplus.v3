<?php
/**
 */
class Modules
{
    public $server_remoto = 'https://multisitio.es';

    public function install($module)
    {
        $local = APP_PATH . "modules/$module.zip";
        if ( ! is_readable(APP_PATH . "modules/$module")) {
            @mkdir(APP_PATH . "modules", true);
        }
        $remoto = file_get_contents("$this->server_remoto/modules/$module.zip");
        file_put_contents($local, $remoto);

        $zip = new ZipArchive;
        if ($zip->open($local) === true) {
            $zip->extractTo(APP_PATH . "modules/$module/");
            $zip->close();
        }

        self::copyTree(APP_PATH . "modules/$module/app/", APP_PATH);
    }

    public function isInstalled($module)
    {
        return is_readable(APP_PATH . "modules/$module.zip") ? true : false;
    }

    public function read()
    {
        $modules = [];
        $modules = unserialize(file_get_contents("$this->server_remoto/modules.php"));
        return $modules;
    }

    public function readme($module='')
    {
        $file = APP_PATH . "views/admin/$module/readme.phtml";
        if (is_readable($file)) {
            return file_get_contents($file);
        }
    }

    public function sendPost($url, $post_data)
    {
        _curl::post($url, $post_data);
    }

    public function copyTree($from, $to)
    {
        if (is_dir($from)) {
            mkdir($to);
            $files = scandir($from);
            foreach ($files as $file) {
                if ($file == "." || $file == "..") {
                    continue;
                }
                self::copyTree("$from/$file", "$to/$file");
            }
        }
        else if (file_exists($from)) {
            copy($from, $to);
        }
    }

    public function delTree($dir)
    {   
        if ( ! is_readable(APP_PATH . "modules/$dir")) {
            return;
        }

        $files = scandir(APP_PATH . "modules/$dir");
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }

            is_dir(APP_PATH . "modules/$dir/$file")
                ? self::delTree("$dir/$file")
                : unlink(APP_PATH . "modules/$dir/$file");    
        }
        return rmdir(APP_PATH . "modules/$dir");
    }

    public function uninstall($module)
    {   
        self::delTree($module);
        unlink(APP_PATH . "modules/$module.zip");
    }

    public function upload($module)
    {
        $in_local = move_uploaded_file($module['tmp_name'], APP_PATH . "modules/{$module['name']}");

        if ($in_local)  {
            $fc = file_get_contents(APP_PATH . "modules/{$module['name']}");
            $file = ['filename'=>$module['name'], 'filecontents'=>$fc];
            $this->sendPost("$this->server_remoto/modules.php", $file);
        }
        return str_replace('.zip', '', $module['name']);
    }
}
