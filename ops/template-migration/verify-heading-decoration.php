<?php
if (PHP_SAPI !== 'cli' || count($argv) !== 2) {
    exit("Usage: php verify-heading-decoration.php bootstrap.php\n");
}
require $argv[1];
class HeadingDecorationTestPlantillas extends Plantillas
{
    public function compilar() { return true; }
}
$db = Kumbia\ActiveRecord\Db::get();
$db->beginTransaction();
try {
    $templates = (new Plantillas)->all("SELECT * FROM plantillas WHERE usuarios_idu='catalogo' AND nombre IN ('srd20','dragonbane','for_the_quest')");
    $templates[] = (new Plantillas)->first("SELECT * FROM plantillas WHERE nombre NOT IN ('srd20','dragonbane','for_the_quest') LIMIT 1");
    foreach ($templates as $p) {
        $rules = new Plantillas_reglas;
        $migrated = $rules->tieneComponentes($p->idu);
        $editor = new HeadingDecorationTestPlantillas;
        $editor->idu = $p->idu;
        foreach (['0', '1px', '2px', '3px', '0', '2px'] as $width) {
            $settings = [];
            foreach (range(1, 6) as $heading) {
                $settings['decoration_h' . $heading] = $width;
            }
            $saved = $editor->saveSettings($settings, []);
            $css = $p->cssPersonalizado();
            foreach (range(1, 6) as $heading) {
                $level = 'h' . $heading;
                if ($saved->tipografia[$level]['decoration'] !== $width) {
                    throw new RuntimeException('Editor lost heading border width');
                }
                if ($width !== '0' && ($saved->tipografia[$level]['line_height'] !== '1.0'
                    || !str_contains($css, "main {$level} {margin-bottom: 10px !important;}"))) {
                    throw new RuntimeException('Underlined heading spacing differs from editor');
                }
                if ($width === '0' && str_contains($css, "main {$level} {margin-bottom: 10px !important;}")) {
                    throw new RuntimeException('Underline spacing remains when disabled');
                }
                $expected = $migrated
                    ? "main {$level} {text-decoration: none !important; border-bottom: var(--{$level}-decoration, 0) solid currentColor !important;}"
                    : "border-top: var(--{$level}-decoration) solid currentColor;";
                if (!str_contains($css, $expected) || str_contains($css, "main {$level} {text-decoration: underline")) {
                    throw new RuntimeException('Heading border missing from compiled CSS');
                }
            }
        }
        // Full-form saves include decoration even when only line-height changes.
        $saved = $editor->saveSettings(['decoration_h3' => '2px', 'line_height_h3' => '2.2'], []);
        if ($saved->tipografia['h3']['line_height'] !== '2.2') {
            throw new RuntimeException('User cannot override the initial line-height');
        }
        $saved = $editor->saveSettings(['decoration_h3' => '3px', 'line_height_h3' => '2.2'], []);
        if ($saved->tipografia['h3']['line_height'] !== '2.2'
            || str_contains($p->cssPersonalizado(), 'main h3 {line-height: 1.0 !important;')) {
            throw new RuntimeException('Underline width or CSS imposes line-height');
        }
        $editor->saveSettings(['decoration_h3' => '0'], []);
        $saved = $editor->saveSettings(['decoration_h3' => '2px', 'line_height_h3' => '2.2'], []);
        if ($saved->tipografia['h3']['line_height'] !== '1.0') {
            throw new RuntimeException('Activating underline should initialize line-height');
        }
    }
    echo 'PASS: all ', count($templates), " templates, H1-H6, off/1/2/3px, return to base, editor and compiled block borders; rollback\n";
} finally {
    $db->rollBack();
}
