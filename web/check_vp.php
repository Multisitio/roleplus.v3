<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1:3306;dbname=role_plus;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT * FROM vistas_previas ORDER BY id DESC LIMIT 5");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "=== LAST VISTAS PREVIAS ===\n";
    print_r($rows);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
