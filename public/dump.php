<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../private/config/Mysqldump.php';

try {
    $dump = new Ifsnop\Mysqldump\Mysqldump('mysql:host=127.0.0.1;port=3306;dbname=role_plus;charset=utf8mb4', 'root', '');
    $dump->start(__DIR__ . '/../private/config/local.sql');
    echo "Dump complete! Saved to local.sql";
} catch (\Exception $e) {
    echo 'mysqldump-php error: ' . $e->getMessage();
}
