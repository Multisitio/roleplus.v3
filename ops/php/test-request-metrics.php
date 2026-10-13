<?php
define('APP_PATH','/test/private/');
require __DIR__.'/roleplus-request-prepend.php';
$_SERVER=['REQUEST_METHOD'=>'POST','REQUEST_URI'=>'/usuarios/confirmar/private-email/private-token?key=secret'];
$_SESSION=['KUMBIA_SESSION'=>[APP_PATH=>['default'=>['idu'=>'private-user']]]];
http_response_code(200);$record=RoleplusRequestMetrics::collect(10,10.02);
if ($record['route']!=='/usuarios/confirmar' || $record['php_ms']!==20.0 || $record['sample_every']!==20 || ! $record['authenticated']) { throw new RuntimeException('MediciÃƒÂ³n incorrecta'); }
$json=json_encode($record);foreach(['private-email','private-token','secret','private-user'] as $secret) { if (str_contains($json,$secret)) { throw new RuntimeException('Datos privados registrados'); } }
$_SERVER['REQUEST_URI']='/index.php/ev/manuales/ia_html?prompt=secret';
http_response_code(503);$record=RoleplusRequestMetrics::collect(10,12);
if ($record['route']!=='/ev/manuales/ia_html' || $record['sample_every']!==1 || $record['php_ms']!==2000.0) { throw new RuntimeException('PeticiÃƒÂ³n lenta o error sin medir'); }
if (RoleplusRequestMetrics::route('/private-token')!=='other' || RoleplusRequestMetrics::route('/img/private-user/private-file.png')!=='/img') { throw new RuntimeException('Ruta sin anonimizar'); }

$start = 1700000002.0;
$_SERVER['REQUEST_URI']='/publicaciones';
http_response_code(200);
$_SERVER['HTTP_X_ROLEPLUS_REQUEST_START']='t=1700000000000000';
$record=RoleplusRequestMetrics::collect($start,$start+0.02);
if ($record['pre_php_ms']!==2000.0 || abs($record['server_to_php_end_ms']-2020)>0.01 || $record['sample_every']!==1) { throw new RuntimeException('Espera previa perdida por muestreo'); }
foreach ([null,'','t=0','t=1','t=1700000003000000','t=1700000000000000, t=1700000000000000','t=secret'] as $header) {
    $_SERVER['HTTP_X_ROLEPLUS_REQUEST_START']=$header;
    if (RoleplusRequestMetrics::beforePhp($start)!==null) { throw new RuntimeException('Sello invalido convertido a una duracion'); }
}
$_SERVER['HTTP_X_ROLEPLUS_REQUEST_START']='t=1700000002000000';
if (RoleplusRequestMetrics::beforePhp($start)!==0.0) { throw new RuntimeException('Cero valido descartado'); }
unset($_SERVER['HTTP_X_ROLEPLUS_REQUEST_START']);
$_SERVER['REQUEST_URI']='/ev/manuales/ia_html';
$record=RoleplusRequestMetrics::collect($start,$start+0.02);
if ($record['sample_every']!==1 || $record['pre_php_ms']!==null || $record['server_to_php_end_ms']!==null) { throw new RuntimeException('IA rapida sin registrar o sello ausente inventado'); }
if (RoleplusRequestMetrics::route('/registrados/youtube/private-id')!=='/registrados/youtube') { throw new RuntimeException('Youtube mezclado con otras rutas privadas'); }

$_SERVER['REQUEST_URI']='/publicaciones';
$record=RoleplusRequestMetrics::collect($start,$start+0.02);
if ($record['sample_every']!==20) { throw new RuntimeException('Muestreo ordinario alterado'); }
echo "OK: tramo Apache/PHP, espera previa sin muestreo, sellos invalidos como null, IA completa y rutas privadas anonimizadas\n";
