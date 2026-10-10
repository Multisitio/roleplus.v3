<?php
if (PHP_SAPI !== 'cli' || count($argv) !== 3) {
    exit("Usage: php retire-body-margin.php bootstrap.php backup.json\n");
}
require $argv[1];
$db = Kumbia\ActiveRecord\Db::get();
$where = "propiedad IN ('--body-margin-top','--componentes-base-body-margin-top')";
$rows = $db->query("SELECT * FROM plantillas_reglas WHERE {$where}")->fetchAll(PDO::FETCH_ASSOC);
if (file_exists($argv[2]) || file_put_contents($argv[2], json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
    throw new RuntimeException('A new backup file is required');
}
$db->beginTransaction();
try {
    $db->exec("DELETE FROM plantillas_reglas WHERE {$where}");
    $templates = (new Plantillas)->all('SELECT * FROM plantillas');
    foreach ($templates as $template) {
        if (str_contains($template->cssPersonalizado(), '--body-margin-top') || ! $template->compilar()) {
            throw new RuntimeException('Obsolete margin remains or compilation failed');
        }
    }
    $folder = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/css/plantillas/';
    $files = [];
    foreach (glob($folder . '*.css') as $path) {
        if (str_ends_with($path, '.min.css')) {
            continue;
        }
        $css = file_get_contents($path);
        $minPath = substr($path, 0, -4) . '.min.css';
        if ( ! str_contains($css, '--body-margin-top')
            && ( ! file_exists($minPath) || ! str_contains(file_get_contents($minPath), '--body-margin-top'))) {
            continue;
        }
        $files[$path] = $css;
    }
    if (file_put_contents($argv[2] . '.files.json', json_encode($files, JSON_PRETTY_PRINT)) === false) {
        throw new RuntimeException('CSS backup failed');
    }
    foreach ($files as $path => $css) {
        $css = preg_replace('/margin-top\s*:\s*var\(--body-margin-top\)\s*(?:!important)?\s*;?/', '', $css);
        $css = preg_replace('/--body-margin-top\s*:[^;}]*;?/', '', $css);
        if (file_put_contents($path, $css) === false) {
            throw new RuntimeException('CSS write failed');
        }
        // Regenerate from the source using the project's portable CSS minifier rules.
        $minified = preg_replace('~/\*[\s\S]*?\*/~', '', $css);
        $minified = preg_replace('/\s+/', ' ', $minified);
        $minified = preg_replace('/\s*([{};,])\s*/', '$1', $minified);
        $minified = preg_replace('/:\s+/', ':', $minified);
        $minified = trim(str_replace(';}', '}', $minified));
        if (file_put_contents($minPath = substr($path, 0, -4) . '.min.css', $minified) === false
            || str_contains(file_get_contents($minPath), '--body-margin-top')) {
            throw new RuntimeException('CSS minification failed');
        }
    }
    echo 'Retired residue in ', count($files), " historical CSS files\n";
    $db->commit();
    echo 'Removed ', count($rows), ' variables; compiled and verified ', count($templates), " templates\n";
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}
