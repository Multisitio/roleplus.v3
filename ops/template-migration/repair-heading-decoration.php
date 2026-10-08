<?php
/** Move the original heading border into the ordinary editable control. */
if (PHP_SAPI !== 'cli' || count($argv) !== 3) {
    exit("Usage: php repair-heading-decoration.php bootstrap.php backup.json\n");
}
require $argv[1];
$db = Kumbia\ActiveRecord\Db::get();
$rows = [];
$read = $db->prepare('SELECT * FROM plantillas_reglas WHERE plantillas_idu=?');
foreach ($db->query("SELECT idu, usuarios_idu FROM plantillas WHERE nombre IN ('srd20','dragonbane')")->fetchAll(PDO::FETCH_ASSOC) as $template) {
    $read->execute([$template['idu']]);
    foreach ($read->fetchAll(PDO::FETCH_ASSOC) as $rule) {
        $rule['usuarios_idu'] = $template['usuarios_idu'];
        $rows[] = $rule;
    }
}
if (file_exists($argv[2]) || file_put_contents($argv[2], json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
    throw new RuntimeException('A new backup file is required');
}
$templates = array_unique(array_column($rows, 'plantillas_idu'));
$db->beginTransaction();
try {
    foreach ($templates as $idu) {
        $rules = new Plantillas_reglas;
        if ( ! $rules->tieneComponentes($idu)) {
            continue;
        }
        $vars = $rules->leer($idu);
        // Idempotent: only the incomplete migration used a zero baseline.
        if (($vars['--componentes-base-h3-decoration'] ?? null) !== '0') {
            continue;
        }
        $rules->guardar($idu, '--componentes-base-h3-decoration', '2px');
        $catalogue = array_filter($rows, fn($r) => $r['plantillas_idu'] === $idu && $r['usuarios_idu'] === 'catalogo');
        // User templates keep every selected value, including an explicit zero.
        if ($catalogue && ($vars['--h3-decoration'] ?? '0') === '0') {
            $rules->guardar($idu, '--h3-decoration', '2px');
        }
        // Preserve edited decorative rules; move only the known historical border.
        foreach ($rows as $row) {
            if ($row['plantillas_idu'] === $idu
                && preg_match('/^main h3\s*\/\* bloque \d+ \*\/$/', $row['selector'])
                && $row['propiedad'] === 'border-bottom'
                && in_array($row['valor'], ['2px solid var(--color-border-primary)', 'var(--border-h3)'], true)) {
                $stmt = $db->prepare('DELETE FROM plantillas_reglas WHERE id=? AND valor=?');
                $stmt->execute([$row['id'], $row['valor']]);
            }
        }
        echo "Mapped heading border: {$idu}\n";
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}
