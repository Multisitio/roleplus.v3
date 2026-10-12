<?php
// Run separately against each environment. Never copy character data between them.
if (PHP_SAPI !== 'cli') { exit(2); }
$db = new PDO($argv[1], $argv[2] ?? 'root', getenv('ROLEPLUS_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('ALTER TABLE personajes ENGINE=InnoDB');
$db->exec('CREATE TABLE IF NOT EXISTS personajes_duplicados_archivo LIKE personajes');
$db->exec('CREATE TABLE IF NOT EXISTS personajes_vinculos_archivo LIKE personajes_usuarios');
$db->exec('CREATE TABLE IF NOT EXISTS personajes_estado (idu VARCHAR(111) NOT NULL PRIMARY KEY, fichas_idu VARCHAR(111) NOT NULL, usuarios_idu VARCHAR(111) NOT NULL, revision BIGINT UNSIGNED NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8');
$db->beginTransaction();
try {
    // Lock the live rows while archiving and consolidating them.
    $db->query('SELECT id FROM personajes FOR UPDATE')->fetchAll();
    $db->query('SELECT id FROM personajes_usuarios FOR UPDATE')->fetchAll();
    $before = (int)$db->query('SELECT COUNT(*) FROM personajes')->fetchColumn();
    $db->exec('INSERT IGNORE INTO personajes_duplicados_archivo SELECT p.* FROM personajes p JOIN (SELECT idu,variable_nombre,MAX(id) AS winner FROM personajes GROUP BY idu,variable_nombre HAVING COUNT(*)>1) d ON p.idu=d.idu AND p.variable_nombre=d.variable_nombre WHERE p.id<>d.winner');
    $db->exec('DELETE p FROM personajes p JOIN (SELECT idu,variable_nombre,MAX(id) AS winner FROM personajes GROUP BY idu,variable_nombre HAVING COUNT(*)>1) d ON p.idu=d.idu AND p.variable_nombre=d.variable_nombre WHERE p.id<>d.winner');
    $db->exec('INSERT IGNORE INTO personajes_vinculos_archivo SELECT p.* FROM personajes_usuarios p JOIN (SELECT personajes_idu,MAX(id) AS winner FROM personajes_usuarios GROUP BY personajes_idu HAVING COUNT(*)>1) d ON p.personajes_idu=d.personajes_idu WHERE p.id<>d.winner');
    $db->exec('DELETE p FROM personajes_usuarios p JOIN (SELECT personajes_idu,MAX(id) AS winner FROM personajes_usuarios GROUP BY personajes_idu HAVING COUNT(*)>1) d ON p.personajes_idu=d.personajes_idu WHERE p.id<>d.winner');
    $db->exec('INSERT IGNORE INTO personajes_estado (idu,fichas_idu,usuarios_idu) SELECT p.idu,p.fichas_idu,COALESCE(u.usuarios_idu,p.usuarios_idu) FROM personajes p JOIN (SELECT idu,MAX(id) AS winner FROM personajes GROUP BY idu) d ON p.id=d.winner LEFT JOIN personajes_usuarios u ON u.personajes_idu=p.idu');
    // Keep orphan links: they may be needed to recover a missing character.
    $db->exec('INSERT IGNORE INTO personajes_estado (idu,fichas_idu,usuarios_idu) SELECT personajes_idu,fichas_idu,usuarios_idu FROM personajes_usuarios');
    $db->exec('UPDATE personajes p JOIN personajes_estado e ON e.idu=p.idu SET p.usuarios_idu=e.usuarios_idu');
    $after = (int)$db->query('SELECT COUNT(*) FROM personajes')->fetchColumn();
    $db->commit();
    foreach (['personajes' => ['personaje_campo', 'idu,variable_nombre'], 'personajes_usuarios' => ['personaje_vinculado', 'personajes_idu']] as $table => [$key, $cols]) {
        $exists = $db->query("SHOW INDEX FROM $table WHERE Key_name='$key'")->fetch();
        if ( ! $exists) { $db->exec("ALTER TABLE $table ADD UNIQUE KEY $key ($cols)"); }
    }
    echo json_encode(['database'=>$db->query('SELECT DATABASE()')->fetchColumn(), 'before'=>$before, 'after'=>$after, 'archived'=>$before-$after]), PHP_EOL;
} catch (Throwable $e) {
    if ($db->inTransaction()) { $db->rollBack(); }
    throw $e;
}
