<?php
if (PHP_SAPI !== 'cli' || count($argv) !== 2) {
    exit("Usage: php verify-block-gap.php bootstrap.php\n");
}
require $argv[1];
class BlockGapTestPlantillas extends Plantillas
{
    public function compilar()
    {
        return true;
    }
}
$db = Kumbia\ActiveRecord\Db::get();
$db->beginTransaction();
try {
    $templates = (new Plantillas)->all('SELECT * FROM plantillas');
    foreach ($templates as $template) {
        $rules = new Plantillas_reglas;
        $editor = new BlockGapTestPlantillas;
        $editor->idu = $template->idu;
        $rules->eliminar_var($template->idu, '--block-gap');
        if ($template->getSettings()->block_gap !== 15) {
            throw new RuntimeException('Default spacing is not 15px');
        }
        $before = $rules->leer($template->idu);
        foreach ([0, 27, 15] as $gap) {
            $editor->saveSettings(['block_gap' => $gap], []);
            if ($template->getSettings()->block_gap !== $gap
                || ! str_contains($template->cssPersonalizado(), '--block-gap: ' . $gap . 'px;')) {
                throw new RuntimeException('Spacing does not reach editor and compiled CSS');
            }
        }
        $editor->saveSettings(['block_gap' => -5], []);
        if ($template->getSettings()->block_gap !== 0) {
            throw new RuntimeException('Negative spacing accepted');
        }
        $after = $rules->leer($template->idu);
        unset($before['--block-gap'], $after['--block-gap']);
        if ($before !== $after) {
            throw new RuntimeException('Unrelated template settings changed');
        }
        // A customized paragraph margin must not override the h+p grouping.
        $rules->guardar($template->idu, '--p-margin-top', '23px');
        if ($template->tieneComponentes()
            && (str_contains($template->cssPersonalizado(), 'main p { margin-top:')
                || str_contains($template->cssPersonalizado(), 'main p:not(:is(h1, h2, h3, h4, h5, h6) + p) { margin-top:'))) {
            throw new RuntimeException('Paragraph margin still separates heading and paragraph');
        }
        if (str_contains($template->cssPersonalizado(), 'main p + p {')) {
            throw new RuntimeException('Old paragraph spacing still compiled');
        }
    }
    echo 'PASS: all ', count($templates), " templates; default/custom/zero spacing, preserved settings; rollback\n";
} finally {
    $db->rollBack();
}
