<?php
if (PHP_SAPI !== 'cli' || count($argv) !== 2) {
    exit("Usage: php verify-cell-padding.php bootstrap.php\n");
}
require $argv[1];
$db = Kumbia\ActiveRecord\Db::get();
$db->beginTransaction();
try {
    $templates = (new Plantillas)->all("SELECT * FROM plantillas WHERE usuarios_idu='catalogo' AND nombre IN ('dragonbane','srd20','for_the_quest')");
    foreach ($templates as $template) {
        $rules = new Plantillas_reglas;
        $vars = $rules->leer($template->idu);
        foreach (['x' => ['left', 'right'], 'y' => ['top', 'bottom']] as $axis => $sides) {
            $key = '--table-td-padding-' . $axis;
            $base = $vars['--componentes-base-table-td-padding-' . $axis];
            foreach (['17px', '0px', $base] as $value) {
                $rules->guardar($template->idu, $key, $value);
                $css = $template->cssPersonalizado();
                if (!str_contains($css, $key . ': ' . $value . ';')) {
                    throw new RuntimeException('Saved cell padding missing');
                }
                foreach ($sides as $side) {
                    if (!str_contains($css, "main :is(td, th) {padding-{$side}: var({$key}) !important;}")) {
                        throw new RuntimeException('Cell padding missing when returning to baseline');
                    }
                }
            }
        }
    }
    echo "PASS: horizontal/vertical cell padding, custom/zero/base values, all three migrated templates; rollback\n";
} finally {
    $db->rollBack();
}
