<?php
if (PHP_SAPI !== 'cli' || count($argv) !== 2) {
    exit("Usage: php verify-deletion.php bootstrap.php\n");
}
require $argv[1];
$db = Kumbia\ActiveRecord\Db::get();
$db->beginTransaction();
try {
    $plantilla = (new Plantillas)->first('SELECT * FROM plantillas WHERE nombre=? AND usuarios_idu=?', ['dragonbane', 'catalogo']);
    $idu = bin2hex(random_bytes(8));
    $variable = bin2hex(random_bytes(8));
    $componentes = new Plantillas_componentes;
    Plantillas_reglas::query('INSERT INTO plantillas_reglas (idu,plantillas_idu,selector,propiedad,valor,peso) VALUES (?,?,?,?,?,?)',
        [$idu, $plantilla->idu, 'main .prueba_borrado', 'color', 'red', 9000]);
    Plantillas_reglas::query('INSERT INTO plantillas_reglas (idu,plantillas_idu,selector,propiedad,valor,peso) VALUES (?,?,?,?,?,?)',
        [$variable, $plantilla->idu, Plantillas_reglas::SELECTOR_VARIABLES, '--prueba-borrado', '1', 9001]);
    foreach ([['otra_plantilla', $idu], [$plantilla->idu, $variable]] as [$destino, $regla]) {
        try {
            $componentes->eliminarRegla($destino, $regla);
            throw new RuntimeException('Deletion outside the allowed decorative rule was accepted');
        } catch (InvalidArgumentException $e) {
            // Expected: another template and common variables are protected.
        }
    }
    if ( ! str_contains($plantilla->cssPersonalizado(), 'main .prueba_borrado')) {
        throw new RuntimeException('Test rule missing from compiled CSS');
    }
    $componentes->eliminarRegla($plantilla->idu, $idu);
    if (str_contains($plantilla->cssPersonalizado(), 'main .prueba_borrado')) {
        throw new RuntimeException('Deleted rule still present in compiled CSS');
    }
    if ( ! Plantillas_reglas::first('SELECT idu FROM plantillas_reglas WHERE idu=?', [$variable])) {
        throw new RuntimeException('Common variable was deleted');
    }
    echo "PASS: scoped deletion, common variables protected, compiled CSS updated; transaction rolled back\n";
} finally {
    $db->rollBack();
}
