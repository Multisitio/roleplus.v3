<?php
require 'private/config/databases.php';
$pdo = new PDO('mysql:host=127.0.0.1:3306;dbname=role_plus;charset=utf8mb4', 'root', '');
$stmt = $pdo->query("SELECT css_personalizado FROM plantillas_manuales WHERE manuales_idu='288e6fab2bf5'");
echo $stmt->fetchColumn();
