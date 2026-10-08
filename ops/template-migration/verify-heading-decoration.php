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
                $expected = $migrated
                    ? "main {$level} {text-decoration: none !important; border-bottom: var(--{$level}-decoration, 0) solid currentColor !important;}"
                    : "border-top: var(--{$level}-decoration) solid currentColor;";
                if (!str_contains($css, $expected) || str_contains($css, "main {$level} {text-decoration: underline")) {
                    throw new RuntimeException('Heading border missing from compiled CSS');
                }
            }
        }
    }
    echo 'PASS: all ', count($templates), " templates, H1-H6, off/1/2/3px, return to base, editor and compiled block borders; rollback\n";
} finally {
    $db->rollBack();
}
