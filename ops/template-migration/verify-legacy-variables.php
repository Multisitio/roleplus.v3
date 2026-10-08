<?php
if (PHP_SAPI !== 'cli' || count($argv) !== 2) {
    exit("Usage: php verify-legacy-variables.php bootstrap.php\n");
}
require $argv[1];
$db = Kumbia\ActiveRecord\Db::get();
$db->beginTransaction();
try {
    $components = new Plantillas_componentes;
    $templates = (new Plantillas)->all("SELECT * FROM plantillas WHERE usuarios_idu='catalogo' AND nombre IN ('dragonbane','srd20','for_the_quest')");
    foreach ($templates as $template) {
        $variables = $components->variablesAnteriores($template->idu);
        if (!$variables) {
            throw new RuntimeException('Legacy variable block is empty');
        }
        foreach ($variables as $variable) {
            if (str_starts_with($variable->propiedad, '--componentes-') || $variable->propiedad === '--h3-family') {
                throw new RuntimeException('Internal or modern variable exposed');
            }
        }
        $variable = array_values($variables)[0];
        $components->guardarValor($template->idu, $variable->idu, 'var(--color-primary)');
        $vars = (new Plantillas_reglas)->leer($template->idu);
        if ($vars[$variable->propiedad] !== 'var(--color-primary)'
            || !str_contains($template->cssPersonalizado(), $variable->propiedad . ': var(--color-primary);')) {
            throw new RuntimeException('Legacy edit not persisted or compiled');
        }
        $hidden = (new Plantillas_reglas)->first('SELECT * FROM plantillas_reglas WHERE plantillas_idu=? AND propiedad=?', [$template->idu, '--componentes-version']);
        foreach ([[$template->idu, $hidden->idu, '0'], ['wrong-template', $variable->idu, 'red'], [$template->idu, $variable->idu, 'red; color: blue']] as $args) {
            try {
                $components->guardarValor(...$args);
            } catch (InvalidArgumentException $e) {
                continue;
            }
            throw new RuntimeException('Invalid legacy variable edit accepted');
        }
    }
    echo "PASS: native variable lists, protected controls/metadata, scoped edits, validation and compiled values; rollback\n";
} finally {
    $db->rollBack();
}
