<?php
if (PHP_SAPI !== 'cli') { exit(2); }
$db = new PDO($argv[1], $argv[2] ?? 'root', getenv('ROLEPLUS_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if ( ! $db->query("SHOW COLUMNS FROM personajes_estado LIKE 'eliminado'")->fetch()) {
    $db->exec('ALTER TABLE personajes_estado ADD COLUMN eliminado TINYINT(1) NOT NULL DEFAULT 0');
}
echo "CHARACTER_DELETION_PROTECTED_SCHEMA\n";
