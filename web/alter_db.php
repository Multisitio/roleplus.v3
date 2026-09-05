<?php
require '../private/config/databases.php';
$db = $databases['default'];
try {
    $pdo = new PDO($db['dsn'], $db['username'], $db['password'], $db['params']);
    
    // Add peso column if not exists
    $pdo->exec("ALTER TABLE plantillas_reglas ADD COLUMN peso INT(11) DEFAULT 0 AFTER plantillas_idu");
    
    echo "Column added.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
