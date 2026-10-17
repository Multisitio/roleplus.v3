<?php
class EvController {}
class Input { public static function post($key) { return $_POST[$key] ?? null; } }
class View { public static function select($value) {} public static function template($value) {} }
class Personajes { public function eliminar($idu) { throw new LogicException('Unconfirmed deletion reached model'); } }
require dirname(__DIR__,2).'/private/controllers/ev/personajes_controller.php';
foreach ([[], ['confirmar_eliminacion'=>'different']] as $extra) {
    $_POST=['idu'=>'012345abcdef']+$extra;
    ob_start(); (new PersonajesController)->eliminar(); ob_end_clean();
    if (http_response_code()!==400) { throw new LogicException('Unconfirmed deletion not rejected'); }
}
echo "PASS: implicit Enter and mismatched confirmation cannot delete",PHP_EOL;
