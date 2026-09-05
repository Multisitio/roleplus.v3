<?php
$pdo = new PDO('mysql:host=127.0.0.1:3306;dbname=c1_roleplus;charset=utf8mb4', 'c1_db_user', '_BeQrU6o');

$stmt = $pdo->prepare("SELECT * FROM plantillas WHERE nombre = 'galactica' OR idu = 'plt37f1a64151'");
$stmt->execute();
echo "--- PLANTILLAS ---\n";
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt2 = $pdo->prepare("SELECT idu, nombre, plantilla, usuarios_idu FROM manuales WHERE idu = 'b460a2621eea'");
$stmt2->execute();
echo "--- MANUAL ---\n";
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
