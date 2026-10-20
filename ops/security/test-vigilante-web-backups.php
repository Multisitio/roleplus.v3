<?php
require __DIR__ . '/vigilante-web-backups.php';
$plan = ['version' => 1, 'available_bytes' => 40 * 1024 ** 3, 'sites' => [[
    'before' => ['domain_id' => 1, 'domain' => 'example.test', 'document_root' => '/example',
        'backup_interval' => 'none', 'backup_copies' => 1, 'backup_format_web' => 'default'],
    'measured_bytes' => 1024, 'vhost_sha256' => str_repeat('a', 64)
]]];
if (count(validate_web_backups($plan)) !== 1) {
    throw new RuntimeException('Plan valido rechazado');
}
$invalid = [];
$p = $plan; $p['available_bytes'] = 1024; $invalid[] = $p;
$p = $plan; $p['sites'][] = $p['sites'][0]; $invalid[] = $p;
$p = $plan; $p['sites'][0]['before']['backup_interval'] = 'daily'; $invalid[] = $p;
$p = $plan; $p['sites'][0]['before']['ssl'] = 'n'; $invalid[] = $p;
$p = $plan; $p['sites'][0]['measured_bytes'] = -1; $invalid[] = $p;
foreach ($invalid as $p) {
    try {
        validate_web_backups($p);
    } catch (RuntimeException $ok) {
        continue;
    }
    throw new RuntimeException('Plan peligroso aceptado');
}
echo "6 comprobaciones correctas\n";
