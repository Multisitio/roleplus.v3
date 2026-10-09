<?php
/** Repair only the requested template; keep drop-cap styling and user values. */
if (PHP_SAPI !== 'cli' || count($argv) !== 4) {
    exit("Usage: php repair-column-dropcap.php bootstrap.php template-id backup.json\n");
}
require $argv[1];
$db = Kumbia\ActiveRecord\Db::get();
$read = $db->prepare('SELECT * FROM plantillas_reglas WHERE plantillas_idu=?');
$read->execute([$argv[2]]);
$rows = $read->fetchAll(PDO::FETCH_ASSOC);
if (file_exists($argv[3]) || file_put_contents($argv[3], json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
    throw new RuntimeException('A new backup file is required');
}
$old = 'section:has(h1) + section > div:first-child > p:first-child';
$new = ':is(header, section):has(h1):not(:has(p)) + section > div:first-child > p:first-child';
$db->beginTransaction();
try {
    $count = 0;
    foreach ($rows as $row) {
        $field = $row['propiedad'] === '--componentes-capitular-selector' ? 'valor' : 'selector';
        if (str_contains($row[$field], $old) && str_contains($row[$field], '::first-letter')) {
            $update = $db->prepare("UPDATE plantillas_reglas SET {$field}=? WHERE id=? AND plantillas_idu=?");
            $update->execute([str_replace($old, $new, $row[$field]), $row['id'], $argv[2]]);
            $count++;
        }
    }
    $template = (new Plantillas)->first('SELECT * FROM plantillas WHERE idu=?', [$argv[2]]);
    if ( ! $template || ! $template->compilar()) {
        throw new RuntimeException('Compilation failed');
    }
    $db->commit();
    echo "Updated selectors: {$count}; template compiled\n";
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}
