<?php
/** Activa solo las copias web revisadas; no regenera servidores virtuales. */
function validate_web_backups(array $plan): array
{
    if (($plan['version'] ?? null) !== 1 || empty($plan['sites']) || count($plan['sites']) > 20) {
        throw new RuntimeException('Plan no valido');
    }
    $ids = [];
    $total = 0;
    foreach ($plan['sites'] as $site) {
        $before = $site['before'] ?? [];
        if (array_keys($before) !== ['domain_id', 'domain', 'document_root', 'backup_interval', 'backup_copies', 'backup_format_web']
            || !is_int($before['domain_id']) || $before['domain_id'] < 1
            || isset($ids[$before['domain_id']])
            || !in_array($before['backup_interval'], ['', 'none'], true)
            || !is_int($before['backup_copies']) || $before['backup_copies'] > 2
            || !is_int($site['measured_bytes'] ?? null) || $site['measured_bytes'] < 0
            || !preg_match('/\A[a-f0-9]{64}\z/', $site['vhost_sha256'] ?? '')) {
            throw new RuntimeException('Sitio no valido o ya configurado');
        }
        $ids[$before['domain_id']] = true;
        $total += $site['measured_bytes'];
    }
    // Dos retenidas mas una en generacion y 20 GiB libres, sin asumir compresion.
    if (!is_int($plan['available_bytes'] ?? null)
        || $plan['available_bytes'] < 3 * $total + 20 * 1024 ** 3) {
        throw new RuntimeException('Margen de espacio insuficiente');
    }
    return $plan['sites'];
}

function apply_web_backups(string $path): void
{
    if (PHP_SAPI !== 'cli' || posix_geteuid() !== 0 || is_link($path)
        || fileowner($path) !== 0 || (fileperms($path) & 0077) !== 0
        || fileowner(dirname($path)) !== 0 || (fileperms(dirname($path)) & 0077) !== 0) {
        throw new RuntimeException('Plan privado de administrador requerido');
    }
    $plan = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    $sites = validate_web_backups($plan);
    $plan['available_bytes'] = (int) disk_free_space('/var/backup');
    validate_web_backups($plan);
    $out = fopen(dirname($path) . '/web-backup-policy-result.json', 'x');
    if (!$out) {
        throw new RuntimeException('Ya existe un resultado');
    }
    chmod(dirname($path) . '/web-backup-policy-result.json', 0600);
    fwrite($out, json_encode(['applied' => false, 'plan' => $plan], JSON_PRETTY_PRINT) . "\n");
    fflush($out);
    fsync($out);
    $pdo = new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=dbispconfig', 'root', null,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=3');
    $pdo->beginTransaction();
    try {
        foreach ($sites as $site) {
            $q = $pdo->prepare('SELECT domain_id,domain,document_root,backup_interval,backup_copies,backup_format_web FROM web_domain WHERE domain_id=? AND active=\'y\' AND type=\'vhost\' AND server_id=1 FOR UPDATE');
            $q->execute([$site['before']['domain_id']]);
            if ($q->fetch(PDO::FETCH_ASSOC) != $site['before']
                || !hash_equals($site['vhost_sha256'], hash_file('sha256', $site['vhost_path']))) {
                throw new RuntimeException('La configuracion revisada ha cambiado');
            }
        }
        $q = $pdo->prepare('UPDATE web_domain SET backup_interval=\'weekly\',backup_copies=2,backup_format_web=\'tar_gzip\' WHERE domain_id=?');
        foreach ($sites as $site) {
            $q->execute([$site['before']['domain_id']]);
            if ($q->rowCount() !== 1) {
                throw new RuntimeException('Actualizacion inesperada');
            }
        }
        $pdo->commit();
        fwrite($out, json_encode(['applied' => true, 'completed_at' => time()]) . "\n");
        fflush($out);
        fsync($out);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } finally {
        fclose($out);
    }
    echo json_encode(['sites_updated' => count($sites)]) . "\n";
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if ($argc !== 3 || $argv[1] !== '--apply') {
        exit(2);
    }
    apply_web_backups($argv[2]);
}
