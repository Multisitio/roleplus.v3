<?php
if (PHP_SAPI !== 'cli' || count($argv) < 3) {
    exit("Usage: php verify-compiler.php bootstrap.php temporary-document-root [prepare]\n");
}
require $argv[1];
$_SERVER['DOCUMENT_ROOT'] = $argv[2];
$p = (new Plantillas)->first('SELECT * FROM plantillas WHERE nombre=? AND usuarios_idu=?', ['dragonbane', 'catalogo']);
$folder = rtrim($argv[2], '/\\') . '/css/plantillas';
if ( ! is_dir($folder)) {
    mkdir($folder, 0775, true);
}
$file = $folder . '/' . $p->idu . '.css';
if (($argv[3] ?? '') === 'prepare') {
    file_put_contents($file, 'old CSS');
    chmod($file, 0644);
    echo 'Prepared existing file owned by UID ', fileowner($file), "\n";
    exit;
}
if ( ! $p->compilar() || file_get_contents($file) !== $p->cssPersonalizado()) {
    throw new RuntimeException('CSS compilation/replacement failed');
}
clearstatcache();
if (PHP_OS_FAMILY !== 'Windows' && fileowner($file) !== fileowner($folder)) {
    throw new RuntimeException('Generated CSS belongs to a different user than its directory');
}
if (glob($folder . '/.plantilla-*')) {
    throw new RuntimeException('Temporary compilation files left behind');
}
echo "PASS: atomic replacement, complete CSS, correct owner, no temporary files\n";
