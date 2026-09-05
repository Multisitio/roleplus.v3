<?php
/**
 * Reviews or commits filename replacements produced by migrate-media.php.
 *
 * Dry-run is the default. Applying requires both --apply and a non-empty SQL
 * backup passed through --backup-confirmed. Passwords are read only from the
 * environment variable ROLEPLUS_DB_PASSWORD.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este programa solo puede ejecutarse desde CLI.\n");
    exit(2);
}

$options = getopt('', [
    'manifest:', 'database:', 'host::', 'port::', 'user::',
    'apply', 'backup-confirmed::',
]);
$manifest = isset($options['manifest']) ? realpath($options['manifest']) : false;
$database = (string) ($options['database'] ?? 'role_plus');
$host = (string) ($options['host'] ?? '127.0.0.1');
$port = (int) ($options['port'] ?? 3306);
$user = (string) ($options['user'] ?? 'root');
$apply = array_key_exists('apply', $options);
$backup = isset($options['backup-confirmed']) ? realpath($options['backup-confirmed']) : false;

if (!$manifest || !preg_match('/^[a-z0-9_]+$/i', $database)) {
    fwrite(STDERR, "Se requieren --manifest y un nombre de --database válido.\n");
    exit(2);
}
if ($apply && (!$backup || !is_file($backup) || filesize($backup) < 1)) {
    fwrite(STDERR, "--apply exige --backup-confirmed apuntando a un volcado SQL no vacío.\n");
    exit(2);
}

$columns = [
    'aventuras' => ['fotos'],
    'aventuras_elementos' => ['fotos'],
    'carpetas' => ['fondo'],
    'comentarios' => ['fotos'],
    'conversaciones' => ['imagen'],
    'elementos' => ['fotos'],
    'escenas' => ['fotos'],
    'eventos' => ['fotos'],
    'fichas' => ['fondo_ficha'],
    'fichas_cajas' => ['imagenes'],
    'grupos' => ['fondo_cabecera', 'fondo_general', 'mapa'],
    'manuales' => ['fotos'],
    'manuales_reglas' => ['fotos'],
    'partidas_elementos' => ['fotos'],
    'plantillas' => ['fotos'],
    'publicaciones' => ['fotos'],
    'publicidad' => ['imagen'],
    'rss_entradas' => ['fotos'],
    'usuarios' => ['avatar', 'fondo_cabecera', 'fondo_general'],
];

$mappings = [];
$handle = fopen($manifest, 'rb');
while (($line = fgets($handle)) !== false) {
    $entry = json_decode($line, true);
    $relativeSource = trim(str_replace('\\', '/', (string) ($entry['source'] ?? '')), '/');
    $parts = explode('/', $relativeSource, 2);
    $owner = count($parts) === 2 ? $parts[0] : '';
    $source = basename($relativeSource);
    $target = basename((string) ($entry['target'] ?? ''));
    if ($source === '' || $target === '' || $source === $target) {
        continue;
    }
    $key = $owner . '/' . $source;
    if (isset($mappings[$key]) && $mappings[$key]['target'] !== $target) {
        throw new RuntimeException("El nombre $key tiene destinos incompatibles en el manifiesto.");
    }
    $mappings[$key] = compact('owner', 'source', 'target');
}
fclose($handle);

$password = getenv('ROLEPLUS_DB_PASSWORD');
$pdo = new PDO(
    "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",
    $user,
    $password === false ? '' : $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);

$ownerColumns = [];
foreach (array_keys($columns) as $table) {
    if ($table === 'usuarios') {
        $ownerColumns[$table] = 'idu';
        continue;
    }
    $columnCheck = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');
    $columnCheck->execute([$database, $table, 'usuarios_idu']);
    $ownerColumns[$table] = (int) $columnCheck->fetchColumn() > 0 ? 'usuarios_idu' : null;
}

$report = ['mode' => $apply ? 'apply' : 'dry-run', 'mappings' => count($mappings), 'matches' => 0, 'updated_rows' => 0, 'locations' => [], 'unresolved' => []];
if ($apply) {
    $pdo->beginTransaction();
}
try {
    foreach ($mappings as $mapping) {
        ['owner' => $owner, 'source' => $source, 'target' => $target] = $mapping;
        foreach ($columns as $table => $tableColumns) {
            $ownerColumn = $owner !== '' ? $ownerColumns[$table] : null;
            foreach ($tableColumns as $column) {
                if ($ownerColumn === null) {
                    $countStatement = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE LOCATE(?, `$column`) > 0");
                    $countStatement->execute([$source]);
                    $count = (int) $countStatement->fetchColumn();
                    if ($count > 0) {
                        $report['unresolved'][] = compact('table', 'column', 'owner', 'source', 'target', 'count') + [
                            'reason' => 'La tabla no permite vincular la referencia con el propietario del archivo.',
                        ];
                    }
                    continue;
                }
                $where = "LOCATE(?, `$column`) > 0 AND `$ownerColumn` = ?";
                $countStatement = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE $where");
                $countStatement->execute([$source, $owner]);
                $count = (int) $countStatement->fetchColumn();
                if ($count < 1) {
                    continue;
                }
                if (strtolower(pathinfo($target, PATHINFO_EXTENSION)) === 'mp4' && $table !== 'publicaciones') {
                    $report['unresolved'][] = compact('table', 'column', 'owner', 'source', 'target', 'count') + [
                        'reason' => 'El destino es vídeo y esta vista todavía exige una imagen.',
                    ];
                    continue;
                }
                $report['matches'] += $count;
                $report['locations'][] = compact('table', 'column', 'owner', 'source', 'target', 'count');
                if ($apply) {
                    $update = $pdo->prepare("UPDATE `$table` SET `$column` = REPLACE(`$column`, ?, ?) WHERE $where");
                    $update->execute([$source, $target, $source, $owner]);
                    $report['updated_rows'] += $update->rowCount();
                }
            }
        }
    }
    if ($apply) {
        $pdo->commit();
    }
} catch (Throwable $e) {
    if ($apply && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
