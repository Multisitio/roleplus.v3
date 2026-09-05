<?php
$databases = require 'C:/xampp/htdocs/roleplus.app/private/config/databases.php';
$db = $databases['default'];
try {
    $pdo = new PDO($db['dsn'], $db['username'], $db['password']);
    
    $stmt = $pdo->prepare("SELECT idu, nombre, descripcion FROM manuales_reglas WHERE idu IN ('e7d997bbd868', '702977b70199')");
    $stmt->execute();
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rules as $r) {
        echo "=== {$r['nombre']} ({$r['idu']}) ===\n";
        echo $r['descripcion'] . "\n\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
