<?php
$dsn = "mysql:host=127.0.0.1;dbname=c1_roleplus";
$pdo = new PDO($dsn, "c1_db_user", "_BeQrU6o");
try {
    $pdo->exec("ALTER TABLE manuales ADD COLUMN orientacion varchar(111) DEFAULT 'v' AFTER formato");
    echo "Column 'orientacion' added.\n";
} catch (Exception $e) {
    echo "Error adding column: " . $e->getMessage() . "\n";
}
try {
    $pdo->exec("ALTER TABLE plantillas CHANGE creado creado_at datetime DEFAULT current_timestamp()");
    echo "Column 'creado' renamed to 'creado_at'.\n";
} catch (Exception $e) {
    echo "Error renaming column: " . $e->getMessage() . "\n";
}
