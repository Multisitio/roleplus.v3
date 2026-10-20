<?php
/** Retira las copias completas automaticas; conserva la politica de bases de datos. */
if (PHP_SAPI !== 'cli' || posix_geteuid() !== 0 || $argc !== 2) {
    exit(2);
}
$path = $argv[1];
if (is_link($path) || fileowner($path) !== 0 || (fileperms($path) & 0077)
    || fileowner(dirname($path)) !== 0 || (fileperms(dirname($path)) & 0077)) {
    throw new RuntimeException('Plan privado requerido');
}
$plan = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
$pdo = new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=dbispconfig', 'root', null,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('SET SESSION innodb_lock_wait_timeout=3');
$pdo->beginTransaction();
$before = [];
try {
    foreach ($plan['sites'] as $site) {
        if (!preg_match('/\Aweb([0-9]+)\z/', $site['id'], $matches)) {
            continue;
        }
        $q = $pdo->prepare('SELECT domain,document_root,backup_interval FROM web_domain WHERE domain_id=? AND active=\'y\' AND type=\'vhost\' AND server_id=1 FOR UPDATE');
        $q->execute([(int) $matches[1]]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if ($row !== ['domain' => $site['domain'], 'document_root' => $site['root'],
                'backup_interval' => $site['expected_interval']]) {
            throw new RuntimeException('La configuracion ha cambiado');
        }
        $before[$matches[1]] = $row;
    }
    $record = fopen(dirname($path) . '/native-web-disable-result.jsonl', 'x');
    if (!$record) {
        throw new RuntimeException('No repetir una configuracion ya intentada');
    }
    chmod(dirname($path) . '/native-web-disable-result.jsonl', 0600);
    fwrite($record, json_encode(['applied' => false, 'before' => $before]) . "\n");
    fflush($record);
    fsync($record);
    $q = $pdo->prepare('UPDATE web_domain SET backup_interval=\'none\' WHERE domain_id=?');
    foreach (array_keys($before) as $id) {
        $q->execute([$id]);
    }
    $pdo->commit();
    fwrite($record, json_encode(['applied' => true, 'sites' => count($before)]) . "\n");
    fflush($record);
    fsync($record);
    fclose($record);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}
echo json_encode(['native_full_web_backups_disabled' => count($before)]) . "\n";
