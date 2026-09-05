<?php
$databases = require 'C:/xampp/htdocs/roleplus.app/private/config/databases.php';
$db = $databases['default'];
try {
    $pdo = new PDO($db['dsn'], $db['username'], $db['password']);
    $stmt = $pdo->prepare("SELECT descripcion FROM manuales_reglas WHERE idu = 'e7d997bbd868'");
    $stmt->execute();
    echo substr($stmt->fetchColumn(), 0, 4000);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
