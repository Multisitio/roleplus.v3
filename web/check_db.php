<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
}

const APP_CHARSET = 'UTF-8';
const APP_PATH = 'C:/xampp/htdocs/roleplus.app/private/';
const CMD_PATH = '/usr/local/bin/';
const CORE_PATH = 'C:/xampp/htdocs/KumbiaPHP/core/';
const DOMAIN = 'http:///roleplus.v3/';
const PRODUCTION = false;
const PUB_PATH = 'C:/xampp/htdocs/roleplus.app/public_html/';
const PUBLIC_PATH = '/';
const VENDOR_PATH = 'C:/xampp/htdocs/vendor/';

require CORE_PATH.'kumbia/bootstrap.php';

try {
    $pdo = new PDO('mysql:host=127.0.0.1:3306;dbname=role_plus;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT id, idu, descripcion FROM manuales_reglas");
    $stmt->execute();
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Processing rules...\n";
    $count = 0;
    foreach ($rules as $r) {
        $formatted = Manuales_reglas::formatearHtml($r['descripcion']);
        if ($formatted !== $r['descripcion']) {
            $formatted_md = _html::bbcode($formatted);
            $update = $pdo->prepare("UPDATE manuales_reglas SET descripcion = ?, descripcion_md = ? WHERE id = ?");
            $update->execute([$formatted, $formatted_md, $r['id']]);
            $count++;
        }
    }
    echo "Updated {$count} rules successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
