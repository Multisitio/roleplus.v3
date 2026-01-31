<?php
/** 
 */
class Atalaya_archivos extends LiteRecord
{
    #
    public function carpetasHuerfanas($accion = '')
    {
        $sql = 'SELECT idu, apodo FROM usuarios';
        $usuarios = parent::all($sql);
        $usuarios = parent::arrayBy($usuarios);

        $dir = _server::isLocal()
            ? 'C:/xampp/htdocs/PRIVADO/roleplus.app/public/img/usuarios'
            : '/home/ubuntu/web/roleplus.app/public/img/usuarios';
        $huerfanas = [];
        $parentadas = [];

        if (is_dir($dir)) {
            $folders = scandir($dir);
            foreach ($folders as $folder) {
                if ($folder != '.' && $folder != '..' && is_dir($dir . '/' . $folder)) {
                    if (empty($usuarios[$folder])) {
                        $huerfanas[$folder] = ':-(';

                        if ($accion === 'borrar') {
                            $deleteDirectory = function($dir) use (&$deleteDirectory) {
                                if (!file_exists($dir)) {
                                    return false;
                                }

                                if (!is_dir($dir)) {
                                    return unlink($dir);
                                }

                                foreach (scandir($dir) as $item) {
                                    if ($item == '.' || $item == '..') {
                                        continue;
                                    }

                                    if (!$deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                                        return false;
                                    }
                                }

                                return rmdir($dir);
                            };

                           	_var::flush("Borrando $dir/$folder");
                            $deleteDirectory("$dir/$folder");
                        }
                    } else {
                        $parentadas[$folder] = $usuarios[$folder]->apodo;
                    }
                }
            }
        }

        return [
            'Nº de carpetas huérfanas' => count($huerfanas),
            'Nº de carpetas de usuario' => count($parentadas),
            'Relación carpeta => usuario' => $parentadas
        ];
    }

    #
    public function vistasHuerfanas($accion = '')
    {
        $sql = 'SELECT idu FROM vistas_previas';
        $vistas_previas = parent::all($sql);
        $vistas_previas = parent::arrayBy($vistas_previas, 'idu');

        $dir = _server::isLocal()
            ? 'C:/xampp/htdocs/PRIVADO/roleplus.v3/public/img/vistas_previas'
            : '/home/ubuntu/web/roleplus.app/public/img/vistas_previas';
        $huerfanas = [];
        $parentadas = [];

        if (is_dir($dir)) {
            $folders = scandir($dir);
            foreach ($folders as $folder) {
                if ($folder != '.' && $folder != '..' && is_dir($dir . '/' . $folder)) {
                    if (empty($vistas_previas[$folder])) {
                        $huerfanas[$folder] = ':-(';

                        if ($accion === 'borrar') {
                            $deleteDirectory = function($dir) use (&$deleteDirectory) {
                                if (!file_exists($dir)) {
                                    return false;
                                }

                                if (!is_dir($dir)) {
                                    return unlink($dir);
                                }

                                foreach (scandir($dir) as $item) {
                                    if ($item == '.' || $item == '..') {
                                        continue;
                                    }

                                    if (!$deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                                        return false;
                                    }
                                }

                                return rmdir($dir);
                            };

                           	_var::flush("Borrando $dir/$folder");
                            $deleteDirectory("$dir/$folder");
                        }
                    } else {
                        $parentadas[$folder] = $vistas_previas[$folder]->idu;
                    }
                }
            }
        }

        return [
            'Nº de carpetas huérfanas' => count($huerfanas),
            'Nº de carpetas con vistas' => count($parentadas),
            'Relación carpeta => Vista previa' => $parentadas
        ];
    }
}
