<?php
if (PHP_SAPI !== 'cli' || count($argv) !== 3) {
    exit("Usage: php retire-heading-spacing.php bootstrap.php backup.json\n");
}
require $argv[1];
$rules = (new Plantillas_componentes)->all("SELECT * FROM plantillas_reglas WHERE selector != '.plantilla'");
$obsolete = [];
foreach ($rules as $rule) {
    $selector = trim(preg_replace('/\/\*.*?\*\//s', '', $rule->selector));
    if (preg_match('/^main h[1-6]$/', $selector)
        && (($rule->propiedad === 'margin-bottom' && trim($rule->valor) === 'var(--spacing-heading-bottom)')
            || ($rule->propiedad === 'padding-top' && in_array($selector, ['main h2', 'main h4'], true) && trim($rule->valor) === '15px'))) {
        $obsolete[] = (array)$rule;
    }
}
if ($obsolete) {
    if (file_put_contents($argv[2], json_encode($obsolete, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
        throw new RuntimeException('Backup failed');
    }
    $db = Kumbia\ActiveRecord\Db::get();
    $db->beginTransaction();
    try {
        foreach ($obsolete as $rule) {
            Plantillas_reglas::query('DELETE FROM plantillas_reglas WHERE id=? AND selector=? AND propiedad=? AND valor=?',
                [$rule['id'], $rule['selector'], $rule['propiedad'], $rule['valor']]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}
echo 'Retired: ', count($obsolete), " obsolete heading spacing declarations\n";
