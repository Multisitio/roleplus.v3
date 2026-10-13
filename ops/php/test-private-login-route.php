<?php
$core=sys_get_temp_dir().'/roleplus-login-test-'.bin2hex(random_bytes(6)).'/';
mkdir($core);mkdir($core.'kumbia');file_put_contents($core.'kumbia/controller.php','<?php');define('CORE_PATH',$core);
class GeneralController { public $claves, $usuario, $version; protected function _filtrar_js_recursivo(&$input) {} }
class Session { static $idu;static $closed=0;static function get($key) { return self::$idu; }static function close() { self::$closed++; } }
class Input { static $ajax=false;static function post($key) { return null; }static function isAjax() { return self::$ajax; } }
class View { static $selected;static function select($v,$t) { self::$selected=[$v,$t]; }static function template($v) {} }
class Redirect { static $url;static function to($url) { self::$url=$url; } }
class Usuarios { static $cookieChecks=0;static $marks=0;static function entrarSiGalleta() { self::$cookieChecks++;if (($_COOKIE['test']??'')==='verified') { Session::$idu='user'; } }static function marcarAcceso() { self::$marks++; }static function uno() { return (object)[]; }static function conectados() { return []; } }
class Configuracion { static $calls=0;function todas() { self::$calls++;return []; } }
class _url { static function enviarAlCanal($ch,$url) {} }
$app=dirname(__DIR__,2);if (is_dir($app.'/private/libs')) { $app.='/private'; }
require $app.'/libs/registrados_controller.php';
class ProbePrivate extends RegistradosController { function run() { return $this->initialize(); } }
try {
    $_GET=[];$_POST=[];$_COOKIE=[];$controller=new ProbePrivate();
    $controller->run();
    if (Redirect::$url!=='/usuarios/identificarse' || Configuracion::$calls!==0 || Session::$closed!==1) { throw new RuntimeException('Visitante con trabajo de página o salto extra'); }
    Redirect::$url=null;http_response_code(200);Input::$ajax=true;$controller->run();
    if (http_response_code()!==401 || View::$selected!==['','login'] || Redirect::$url!==null) { throw new RuntimeException('AJAX sin sesión incorrecto'); }
    Input::$ajax=false;$_COOKIE=['test'=>'invalid'];Redirect::$url=null;$controller->run();
    if (Session::$idu || Redirect::$url!=='/usuarios/identificarse') { throw new RuntimeException('Cookie no validada admitida'); }
    $_COOKIE=['test'=>'verified'];Redirect::$url=null;$controller->run();
    if (Session::$idu!=='user' || Redirect::$url!==null || Configuracion::$calls!==1 || Usuarios::$marks!==1) { throw new RuntimeException('Autologin verificado roto'); }
    $checks=Usuarios::$cookieChecks;$controller->run();
    if (Usuarios::$cookieChecks!==$checks || Configuracion::$calls!==2) { throw new RuntimeException('Sesión existente modificada'); }
} finally {
    unlink($core.'kumbia/controller.php');rmdir($core.'kumbia');rmdir($core);
}
echo "OK: login directo, AJAX 401, cookie inválida rechazada, autologin y sesión conservados\n";
