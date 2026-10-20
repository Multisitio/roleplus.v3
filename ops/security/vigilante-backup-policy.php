<?php
/** Administracion manual de campos de copias en ISPConfig, sin eventos de vhost. */
function validate_backup_plan(array $plan): array
{
    if (($plan['version'] ?? null) !== 1 || empty($plan['changes']) || count($plan['changes']) > 20) {
        throw new RuntimeException('Plan no valido');
    }
    $seen = [];
    foreach ($plan['changes'] as $change) {
        $before = $change['before'] ?? [];
        $after = $change['after'] ?? [];
        if (array_keys($before) !== ['database_id', 'database_name', 'parent_domain_id', 'backup_interval', 'backup_copies']
            || array_keys($after) !== ['parent_domain_id', 'backup_interval', 'backup_copies']
            || !is_int($before['database_id']) || $before['database_id'] < 1
            || !preg_match('/\Ac1_[a-z0-9_]+\z/', $before['database_name'])
            || !is_int($before['parent_domain_id']) || $before['parent_domain_id'] < 0
            || !is_int($after['parent_domain_id']) || $after['parent_domain_id'] < 1
            || $after['backup_interval'] !== $before['backup_interval']
            || $after['backup_interval'] !== 'weekly'
            || !is_int($before['backup_copies']) || !is_int($after['backup_copies'])
            || $after['backup_copies'] < $before['backup_copies'] || $after['backup_copies'] > 7
            || isset($seen[$before['database_id']])) {
            throw new RuntimeException('Campos o alcance no permitidos');
        }
        if (($before['parent_domain_id'] !== $after['parent_domain_id'])
            && ($before['parent_domain_id'] !== 0 || count($change['evidence'] ?? []) !== 2)) {
            throw new RuntimeException('Cambio de correspondencia no acreditado');
        }
        $seen[$before['database_id']] = true;
        foreach ($change['evidence'] ?? [] as $evidence) {
            if (!is_string($evidence['path'] ?? null) || !str_starts_with($evidence['path'], '/')
                || !preg_match('/\A[a-f0-9]{64}\z/', $evidence['sha256'] ?? '')) {
                throw new RuntimeException('Evidencia no valida');
            }
        }
    }
    return $plan['changes'];
}

function apply_backup_plan(string $path): void
{
    if (PHP_SAPI !== 'cli' || posix_geteuid() !== 0 || is_link($path)
        || fileowner($path) !== 0 || (fileperms($path) & 0077) !== 0
        || fileowner(dirname($path)) !== 0 || (fileperms(dirname($path)) & 0077) !== 0) {
        throw new RuntimeException('Se requiere un plan privado de administrador');
    }
    $changes = validate_backup_plan(json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR));
    $reportPath = dirname($path) . '/backup-policy-result.json';
    // Reserva exclusiva: no sobrescribir ni repetir una aplicacion previa.
    $report = fopen($reportPath, 'x');
    if (!$report) {
        throw new RuntimeException('Ya existe un resultado');
    }
    chmod($reportPath, 0600);
    $pdo = new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=dbispconfig', 'root', null,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=3');
    $pdo->beginTransaction();
    $result = ['version' => 1, 'applied' => false, 'changes' => $changes];
    try {
        foreach ($changes as $change) {
            $before = $change['before'];
            $stmt = $pdo->prepare('SELECT database_id,database_name,parent_domain_id,backup_interval,backup_copies FROM web_database WHERE database_id=? AND active=\'y\' AND server_id=1 FOR UPDATE');
            $stmt->execute([$before['database_id']]);
            $actual = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$actual || $actual != $before) {
                throw new RuntimeException('La configuracion ha cambiado desde la revision');
            }
            $stmt = $pdo->prepare('SELECT domain,document_root FROM web_domain WHERE domain_id=? AND server_id=1 AND active=\'y\' AND type=\'vhost\' FOR UPDATE');
            $stmt->execute([$change['after']['parent_domain_id']]);
            $web = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$web || $web !== $change['website']) {
                throw new RuntimeException('El dominio o su raiz no coinciden');
            }
            foreach ($change['evidence'] ?? [] as $evidence) {
                if (!hash_equals($evidence['sha256'], hash_file('sha256', $evidence['path']))) {
                    throw new RuntimeException('La evidencia ha cambiado');
                }
            }
        }
        foreach ($changes as $change) {
            $stmt = $pdo->prepare('UPDATE web_database SET parent_domain_id=?,backup_interval=?,backup_copies=? WHERE database_id=?');
            $stmt->execute([$change['after']['parent_domain_id'], $change['after']['backup_interval'],
                $change['after']['backup_copies'], $change['before']['database_id']]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Actualizacion inesperada');
            }
        }
        // Antes del COMMIT queda evidencia duradera de lo que se pretendia cambiar.
        fwrite($report, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        fflush($report);
        if (function_exists('fsync')) {
            fsync($report);
        }
        $pdo->commit();
        $result['applied'] = true;
        $result['completed_at'] = time();
        rewind($report);
        ftruncate($report, 0);
        fwrite($report, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        fflush($report);
        if (function_exists('fsync')) {
            fsync($report);
        }
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    } finally {
        fclose($report);
    }
    echo json_encode(['applied' => true, 'databases_updated' => count($changes)]) . "\n";
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    if ($argc !== 3 || $argv[1] !== '--apply') {
        fwrite(STDERR, "Uso: php vigilante-backup-policy.php --apply plan-privado.json\n");
        exit(2);
    }
    apply_backup_plan($argv[2]);
}
