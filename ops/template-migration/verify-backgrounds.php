<?php
if (PHP_SAPI !== 'cli' || count($argv) !== 2) {
    exit("Usage: php verify-backgrounds.php bootstrap.php\n");
}
require $argv[1];
$db = Kumbia\ActiveRecord\Db::get();
$db->beginTransaction();
try {
    $templates = [
        (new Plantillas)->first('SELECT * FROM plantillas WHERE nombre=? AND usuarios_idu=?', ['dragonbane', 'catalogo']),
        (new Plantillas)->first("SELECT * FROM plantillas WHERE nombre NOT IN ('dragonbane','srd20','for_the_quest') LIMIT 1")
    ];
    foreach ($templates as $p) {
        (new Plantillas_reglas)->guardar($p->idu, '--background-image', 'url("/background-test.webp")');
        foreach (['none' => '--background-image', 'url("/even-test.webp")' => '--background-image-even'] as $even => $expected) {
            (new Plantillas_reglas)->guardar($p->idu, '--background-image-even', $even);
            $css = $p->cssPersonalizado();
            preg_match_all('~main\s*>\s*div:nth-child\(even\)(?:\s+article)?\s*\{([^}]+)\}~', $css, $blocks);
            $background = '';
            foreach ($blocks[1] as $block) {
                if (preg_match('/background-image:\s*var\((--[a-z-]+)\)/', $block, $match)) {
                    $background = $match[1];
                }
            }
            if ($background !== $expected) {
                throw new RuntimeException('Wrong even-page background: ' . $p->nombre . '/' . $even);
            }
            if ($even === 'none' && $p->getSettings()->fondo_even_url !== '') {
                throw new RuntimeException('Optional background should remain empty in the editor');
            }
        }
    }
    echo "PASS: empty even background inherits odd background; distinct even background preserved; ordinary and migrated templates; rollback\n";
} finally {
    $db->rollBack();
}
