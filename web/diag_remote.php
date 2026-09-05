<?php
$dsn = "mysql:host=127.0.0.1;dbname=c1_roleplus";
$pdo = new PDO($dsn, "c1_db_user", "_BeQrU6o");
$stmt = $pdo->prepare("SELECT selector, propiedad, valor FROM plantillas_reglas WHERE plantillas_idu=? AND propiedad LIKE '%background%'");
$stmt->execute(["b5d8206c7d79"]);
$reglas = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Background Rules Detailed:\n";
print_r($reglas);
