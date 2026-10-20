<?php
// Solo datos sinteticos, sin sesiones, claves reales ni acceso a bases de datos.
require $argv[1];
function check($condition) { if (!$condition) { throw new RuntimeException('Comprobacion fallida'); } }
$recipient = bin2hex(random_bytes(4));
$reference = bin2hex(random_bytes(32));
$data = ['version' => 1, 'recipient' => $recipient, 'observed_at' => time(), 'last_test' => time(),
    'items' => [['reference' => $reference, 'site' => 'example.com', 'kind' => 'php_fatal', 'family' => 'otras',
        'code' => 0, 'count' => 2, 'first_seen' => time() - 60, 'last_seen' => time(), 'observed' => true, 'accepted' => false]]];
check(Vigilante::seleccionar($data, $reference, '') === null);
check(Vigilante::seleccionar($data, $reference, bin2hex(random_bytes(4))) === null);
check(Vigilante::seleccionar($data, '../keys.php', $recipient) === null);
check(Vigilante::seleccionar($data, bin2hex(random_bytes(32)), $recipient) === null);
$result = Vigilante::seleccionar($data, $reference, $recipient);
check($result['selected']['site'] === 'example.com');
check($result['selected']['label'] === 'Error fatal de PHP');
check(Vigilante::seleccionar($data, 'prueba', $recipient)['selected'] === null);
$data['items'][0]['site'] = '<script>malicioso</script>';
check(Vigilante::seleccionar($data, $reference, $recipient) === null);
echo "8 comprobaciones de acceso y seleccion correctas.\n";
