<?php
if (PHP_SAPI !== 'cli' || count($argv) !== 2) {
    exit("Usage: php verify-line-height.php bootstrap.php\n");
}
require $argv[1];
$options = Plantillas::opcionesInterlineado();
if (count($options) !== 21 || $options[0] !== '0.5' || $options[20] !== '2.5' || $options[10] !== '1.5') {
    throw new RuntimeException('Incorrect line-height scale');
}
$db = Kumbia\ActiveRecord\Db::get();
class LineHeightTestPlantillas extends Plantillas
{
    public function compilar()
    {
        return true;
    }
}
$db->beginTransaction();
try {
    $templates = [
        (new Plantillas)->first('SELECT * FROM plantillas WHERE nombre=? AND usuarios_idu=?', ['dragonbane', 'catalogo']),
        (new Plantillas)->first("SELECT * FROM plantillas WHERE nombre NOT IN ('dragonbane','srd20','for_the_quest') LIMIT 1")
    ];
    foreach ($templates as $p) {
        $rules = new Plantillas_reglas;
        $editor = new LineHeightTestPlantillas;
        $editor->idu = $p->idu;
        $rules->guardar($p->idu, '--h3-decoration', '0');
        $rules->guardar($p->idu, '--h3-margin-top', '17px');
        foreach (['0.5', '1.5', '2.5'] as $height) {
            $editor->saveSettings(['line_height_h3' => $height], []);
            if ($p->getSettings()->tipografia['h3']['line_height'] !== $height) {
                throw new RuntimeException('Saved line-height missing from editor');
            }
            $css = $p->cssPersonalizado();
            if ( ! str_contains($css, '--h3-line-height: ' . $height . ';')
                || ! str_contains($css, 'main h3 {line-height: var(--h3-line-height, 1.5) !important;}')) {
                throw new RuntimeException('Line-height missing from compiled CSS');
            }
            if ($p->getSettings()->tipografia['h3']['margin_top'] !== '17px') {
                throw new RuntimeException('Typography margin changed');
            }
        }
        $editor->saveSettings(['line_height_h3' => '9px'], []);
        if ($p->getSettings()->tipografia['h3']['line_height'] !== '2.5') {
            throw new RuntimeException('Invalid line-height accepted');
        }
        $rules->eliminar_var($p->idu, '--h3-line-height');
        if ($p->getSettings()->tipografia['h3']['line_height'] !== '1.5') {
            throw new RuntimeException('Default should be 1.5');
        }
    }
    echo "PASS: scale, default, saved settings, compiled ordinary/migrated CSS, preserved margins; rollback\n";
} finally {
    $db->rollBack();
}
