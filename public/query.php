<?php
$pdo = new PDO('mysql:host=127.0.0.1:3306;dbname=role_plus;charset=utf8mb4', 'root', '');
$stmt = $pdo->query('SHOW TABLES LIKE "%manual%"');
$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($tables)) {
    echo "No manual tables found.\n";
}

foreach ($tables as $tableRow) {
    $table = array_values($tableRow)[0];
    echo "Table: $table\n";
    $stmt = $pdo->query("SELECT * FROM $table WHERE id = '180c5f77583' OR ref = '180c5f77583' OR slug = '180c5f77583'");
    if ($stmt) {
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        print_r($rows);
    }
}
