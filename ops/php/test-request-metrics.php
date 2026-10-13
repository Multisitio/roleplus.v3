<?php
define('APP_PATH','/test/private/');
require __DIR__.'/roleplus-request-prepend.php';
$_SERVER=['REQUEST_METHOD'=>'POST','REQUEST_URI'=>'/usuarios/confirmar/private-email/private-token?key=secret'];
$_SESSION=['KUMBIA_SESSION'=>[APP_PATH=>['default'=>['idu'=>'private-user']]]];
http_response_code(200);$record=RoleplusRequestMetrics::collect(10,10.02);
if ($record['route']!=='/usuarios/confirmar' || $record['php_ms']!==20.0 || $record['sample_every']!==20 || ! $record['authenticated']) { throw new RuntimeException('Medición incorrecta'); }
$json=json_encode($record);foreach(['private-email','private-token','secret','private-user'] as $secret) { if (str_contains($json,$secret)) { throw new RuntimeException('Datos privados registrados'); } }
$_SERVER['REQUEST_URI']='/index.php/ev/manuales/ia_html?prompt=secret';
http_response_code(503);$record=RoleplusRequestMetrics::collect(10,12);
if ($record['route']!=='/ev/manuales/ia_html' || $record['sample_every']!==1 || $record['php_ms']!==2000.0) { throw new RuntimeException('Petición lenta o error sin medir'); }
if (RoleplusRequestMetrics::route('/private-token')!=='other' || RoleplusRequestMetrics::route('/img/private-user/private-file.png')!=='/img') { throw new RuntimeException('Ruta sin anonimizar'); }
echo "OK: rutas cerradas, muestreo, errores y lentitud; sin tokens, usuarios ni query\n";
