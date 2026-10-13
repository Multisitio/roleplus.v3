<?php
class GeneralController {}
class AppController { public function __construct() { throw new RuntimeException('Contexto de página cargado'); } }
class View { static function select($v, $t) { if ($v !== null || $t !== null) { throw new RuntimeException('Layout de imagen'); } } }
class Session { static $closed=0; static function close() { self::$closed++; } }
class Config { static function get($key) { if ($key!=='vistas.miniaturas') { throw new RuntimeException('Contexto de usuario consultado'); } return ['m'=>32]; } }
class Redirect { static $url; static function to($url) { self::$url=$url; } }
class KumbiaException extends RuntimeException {}
$app=dirname(__DIR__,2); if (is_dir($app.'/private/libs')) { $app.='/private'; }
require $app.'/controllers/img_controller.php';
require $app.'/libs/thumbnail.php';
class ProbeImg extends ImgController { function start() { return $this->initialize(); } }
$cwd=getcwd();$dir=sys_get_temp_dir().'/roleplus-image-test-'.bin2hex(random_bytes(6));
mkdir($dir);mkdir($dir.'/img');mkdir($dir.'/img/users');chdir($dir);
try {
    $controller=new ProbeImg();$controller->start();
    if (Session::$closed!==1) { throw new RuntimeException('Sesión no liberada'); }
    for ($n=0;$n<43;$n++) {
        http_response_code(200);ob_start();$controller->__call('users',['m.missing-'.$n.'.png']);$body=ob_get_clean();
        if (http_response_code()!==404 || $body!=='Imagen no encontrada.') { throw new RuntimeException('Ausencia cara o incorrecta'); }
    }
    foreach ([['users',['../m.secret.png']],['users',['m.../../secret.png']],['users',['plain.png']]] as [$action,$params]) {
        http_response_code(200);ob_start();$controller->__call($action,$params);ob_end_clean();
        if (http_response_code()!==404) { throw new RuntimeException('Ruta inválida aceptada'); }
    }
    $img=imagecreatetruecolor(64,32);imagepng($img,'img/users/sample.png');imagedestroy($img);$original=hash_file('sha256','img/users/sample.png');
    $controller->__call('users',['m.sample.png']);$thumb=Redirect::$url;
    if ($thumb!=='img/users/m.sample.png' || getimagesize($thumb)[0]!==32 || getimagesize($thumb)[1]!==16) { throw new RuntimeException('URL o proporciones cambiadas'); }
    touch($thumb,time()+10);clearstatcache();$stamp=filemtime($thumb);$controller->__call('users',['m.sample.png']);clearstatcache();
    if (filemtime($thumb)!==$stamp) { throw new RuntimeException('Miniatura válida regenerada'); }
    touch('img/users/sample.png',time()+20);clearstatcache();$controller->__call('users',['m.sample.png']);clearstatcache();
    if (filemtime($thumb)===$stamp || hash_file('sha256','img/users/sample.png')!==$original) { throw new RuntimeException('Original alterado o miniatura obsoleta'); }
    if (glob('img/users/.thumb-*')) { throw new RuntimeException('Temporal sin retirar'); }
} finally {
    foreach (glob($dir.'/img/users/*') as $file) { unlink($file); }
    chdir($cwd);rmdir($dir.'/img/users');rmdir($dir.'/img');rmdir($dir);
}
echo "OK: 43 ausencias sin contexto de página, rutas seguras, URL histórica, proporciones, caché e invalidación\n";
