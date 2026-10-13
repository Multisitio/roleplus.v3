<?php
class Config { static function get($key) { return 'test-key'; } }
class _curl {
    static $result; static $queue=[]; static $calls=0;
    static function post($url,$data,$options) {
        if (strpos($url,'gemini-3.8-flash:generateContent') === false || ($options[CURLOPT_TIMEOUT] > 60 || $options[CURLOPT_TIMEOUT] < 1) || isset(json_decode($data)->generationConfig->thinkingConfig)) { throw new Exception('Modelo o razonamiento distinto de la prueba autorizada'); }
        self::$calls++; return self::$queue ? array_shift(self::$queue) : self::$result;
    }
}
class EvController {}
class View { static function select($v, $template='') { if ($v !== null || $template !== null) { throw new Exception('La respuesta incluye layout'); } } }
class Input { static function post($v) { return $_POST[$v] ?? null; } }
class Session { static $idu='owner'; static function get($key) { return self::$idu; } static function close() {} }
class Manuales { function uno($id) { return (object)['idu'=>$id,'usuarios_idu'=>'owner','nombre'=>'Manual','alcance'=>'privado']; } }
class Manuales_reglas { function todas($id) { return []; } }
require dirname(__DIR__,2).'/private/libs/_ia.php';
require dirname(__DIR__,2).'/private/controllers/ev/manuales_controller.php';
$_POST=['prompt'=>'Sintetiza','manuales_idu'=>'manual','current_content'=>'<article>Texto</article>'];
function response($errno=0) {
    _curl::$result=(object)['body'=> $errno ? false : json_encode(['candidates'=>[['content'=>['parts'=>[['text'=>'<article>Resultado</article>']]]]],'modelVersion'=>'latest-version']),'info'=>['http_code'=>$errno?0:200],'errno'=>$errno,'ok'=>$errno===0];
    http_response_code(200); ob_start(); (new ManualesController)->ia_html(); $text=ob_get_clean(); return [http_response_code(),$text];
}
[$code,$text]=response(); if ($code!==200 || $text!=='<article>Resultado</article>') { throw new Exception('HTML incorrecto'); }
[$code,$text]=response(CURLE_OPERATION_TIMEDOUT); if ($code!==503 || strpos($text,'60 segundos')===false || strpos($text,'<article>')!==false) { throw new Exception('Timeout incorrecto'); }
response(); $ok=_curl::$result;
$fail=(object)['body'=>json_encode(['error'=>['status'=>'UNAVAILABLE']]),'info'=>['http_code'=>503],'errno'=>0,'ok'=>false];
_curl::$calls=0; _curl::$queue=[$fail,$ok];
ob_start(); (new ManualesController)->ia_html(); $recovered=ob_get_clean();
if (_curl::$calls!==2 || $recovered!=='<article>Resultado</article>') { throw new Exception('No se recuperó el 503 transitorio'); }
_curl::$calls=0; _curl::$queue=[$fail,$fail];
ob_start(); (new ManualesController)->ia_html(); $failed=ob_get_clean();
if (_curl::$calls!==2 || http_response_code()!==503 || strpos($failed,'temporalmente')===false) { throw new Exception('Reintentos ilimitados o error incorrecto'); }
Session::$idu=null; [$code,$text]=response(); if ($code!==401) { throw new Exception('Sesión ausente incorrecta'); }
echo "OK: modelo 3.8 Flash, HTML correcto, timeout 503 y sesión ausente 401\n";
