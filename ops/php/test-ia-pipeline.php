<?php
class Config { static function get($key) { return 'test-key'; } }
class _curl {
    static $result; static $queue=[]; static $calls=0;
    static function post($url,$data,$options) {
        $config=json_decode($data)->generationConfig;
        if (strpos($url,'gemini-4-argon:generateContent') === false || ($options[CURLOPT_TIMEOUT] > 60 || $options[CURLOPT_TIMEOUT] < 1) || $config->maxOutputTokens!==8192 || isset($config->thinkingConfig)) { throw new Exception('Modelo, presupuesto o razonamiento incorrectos'); }
        self::$calls++; return self::$queue ? array_shift(self::$queue) : self::$result;
    }
}
class EvController {}
class View { static function select($v, $template='') { if ($v !== null || $template !== null) { throw new Exception('La respuesta incluye layout'); } } }
class Input { static function post($v) { return $_POST[$v] ?? null; } }
class Session { static $idu='owner'; static function get($key) { return self::$idu; } static function close() {} }
class Manuales { function uno($id) { return (object)['idu'=>$id,'usuarios_idu'=>'owner','nombre'=>'Manual','alcance'=>'privado']; } }
class Manuales_reglas { function todas($id) { return []; } }
$appPath=dirname(__DIR__,2);
if (is_dir($appPath.'/private/libs')) { $appPath.='/private'; }
require $appPath.'/libs/_ia.php';
require $appPath.'/controllers/ev/manuales_controller.php';
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
function candidateResponse($parts,$finish='STOP') {
    _curl::$result=(object)['body'=>json_encode(['candidates'=>[['finishReason'=>$finish,'content'=>['parts'=>$parts]]]]),'info'=>['http_code'=>200],'errno'=>0,'ok'=>true];
    http_response_code(200); ob_start(); (new ManualesController)->ia_html(); return [http_response_code(),ob_get_clean()];
}
[$code,$text]=candidateResponse([['thought'=>true,'text'=>'Razonamiento'],['text'=>'<article>'],['text'=>'Completo</article>']]);
if ($code!==200 || $text!=='<article>Completo</article>') { throw new Exception('No se concatenan todas las partes de salida'); }
[$code,$text]=candidateResponse([['text'=>'<article>Parcial']], 'MAX_TOKENS');
if ($code!==502 || strpos($text,'límite')===false || strpos($text,'<article>')!==false) { throw new Exception('Se incorpora o renderiza salida truncada'); }
[$code,$text]=candidateResponse([['text'=>'<h1>Fragmento sin article</h1>']]);
if ($code!==502 || strpos($text,'página HTML completa')===false || strpos($text,'<h1>')!==false) { throw new Exception('Se renderiza HTML en el error de formato'); }
Session::$idu=null; [$code,$text]=response(); if ($code!==401) { throw new Exception('Sesión ausente incorrecta'); }
echo "OK: Gemini 4 Argon, partes completas, truncamiento protegido, errores sin HTML, timeout y autenticación\n";
