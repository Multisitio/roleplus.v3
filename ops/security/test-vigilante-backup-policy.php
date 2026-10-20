<?php
require __DIR__ . '/vigilante-backup-policy.php';
$change = [
    'before' => ['database_id' => 1, 'database_name' => 'c1_example', 'parent_domain_id' => 0,
        'backup_interval' => 'weekly', 'backup_copies' => 1],
    'after' => ['parent_domain_id' => 2, 'backup_interval' => 'weekly', 'backup_copies' => 7],
    'evidence' => [['path' => '/example/config', 'sha256' => str_repeat('a', 64)],
        ['path' => '/example/vhost', 'sha256' => str_repeat('b', 64)]]
];
$plan = ['version' => 1, 'changes' => [$change]];
if (count(validate_backup_plan($plan)) !== 1) {
    throw new RuntimeException('Se rechazo el plan valido');
}
$invalid = [];
$p = $plan; $p['changes'][0]['after']['backup_interval'] = 'daily'; $invalid[] = $p;
$p = $plan; $p['changes'][0]['after']['backup_copies'] = 0; $invalid[] = $p;
$p = $plan; $p['changes'][0]['after']['active'] = 'n'; $invalid[] = $p;
$p = $plan; $p['changes'][0]['evidence'] = []; $invalid[] = $p;
$p = $plan; $p['changes'][] = $change; $invalid[] = $p;
$p = $plan; $p['changes'][0]['before']['database_name'] = 'bad;sql'; $invalid[] = $p;
foreach ($invalid as $p) {
    try {
        validate_backup_plan($p);
    } catch (RuntimeException $expected) {
        continue;
    }
    throw new RuntimeException('Se acepto un plan fuera de alcance');
}
echo "7 comprobaciones correctas\n";
