<?php
if (PHP_SAPI !== 'cli' || count($argv) < 2) {
    exit("Usage: php retire-boundary-spacing.php bootstrap.php [--apply backup.json]\n");
}
require $argv[1];
$apply = ($argv[2] ?? '') === '--apply';
$rules = (new Plantillas_componentes)->all("SELECT * FROM plantillas_reglas WHERE selector != '.plantilla'");
$obsolete = [];
foreach ($rules as $rule) {
    $selector = trim(preg_replace('/\/\*.*?\*\//s', '', $rule->selector));
    $selector = preg_replace('/\s+/', ' ', $selector);
    $property = match ($selector) {
        'main :not(blockquote h5):first-child' => 'margin-top',
        'main :last-child' => 'margin-bottom',
        default => null,
    };
    if ($property === $rule->propiedad && in_array(trim($rule->valor), ['0', '0px'], true)) {
        $obsolete[] = (array)$rule;
    }
}
if ($apply && $obsolete) {
    if (empty($argv[3]) || file_put_contents($argv[3], json_encode($obsolete, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
        throw new RuntimeException('Backup required before retiring boundary rules');
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
echo $apply ? 'Retired: ' : 'Would retire: ', count($obsolete), " boundary spacing rules\n";
