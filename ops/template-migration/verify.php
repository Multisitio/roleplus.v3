<?php
if (PHP_SAPI !== 'cli' || count($argv) < 2) {
    exit("Usage: php verify.php bootstrap.php [baseline.json]\n");
}
require $argv[1];
$db = Kumbia\ActiveRecord\Db::get();
$iguales = 0;
if (isset($argv[2])) {
    $baseline = json_decode(file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
    foreach ($baseline['plantillas'] as $row) {
        if (in_array($row['nombre'], ['srd20', 'dragonbane', 'for_the_quest'], true)) {
            continue;
        }
        $p = (new Plantillas)->first('SELECT * FROM plantillas WHERE idu=?', [$row['idu']]);
        $antes = preg_replace('~/\* Compilado:.*?\*/~', '', $baseline['css'][$p->idu]);
        $despues = preg_replace('~/\* Compilado:.*?\*/~', '', $p->cssPersonalizado());
        if ($antes !== $despues) {
            throw new RuntimeException('Ordinary template changed: ' . $p->nombre);
        }
        $iguales++;
    }
}
$resultados = [];
$db->beginTransaction();
try {
    foreach (['srd20', 'dragonbane', 'for_the_quest'] as $nombre) {
        $p = (new Plantillas)->first('SELECT * FROM plantillas WHERE nombre=? AND usuarios_idu=?', [$nombre, 'catalogo']);
        if ( ! $p || ! $p->tieneComponentes()) {
            throw new RuntimeException('Missing database preset: ' . $nombre);
        }
        $componentes = new Plantillas_componentes;
        $antes = $componentes->reglas($p->idu);
        $ajustes = $p->getSettings();
        foreach ($ajustes->tipografia as $nivel => $tipo) {
            if ( ! in_array($tipo['size'], Plantillas::opcionesTamano($nivel, $tipo['size']), true)) {
                throw new RuntimeException('Size missing from control: ' . $nombre . '/' . $nivel);
            }
        }
        (new Plantillas_reglas)->guardar($p->idu, '--h4-size', '27px');
        (new Plantillas_reglas)->eliminar_obsoletas($p->idu, array_keys(Plantillas::DEFAULTS));
        $despues = $componentes->reglas($p->idu);
        if (count($antes) !== count($despues) || ! str_contains($p->cssPersonalizado(), 'font-size: var(--h4-size) !important')) {
            throw new RuntimeException('Decoration or common-control regression: ' . $nombre);
        }
        $copia = (new Plantillas)->obtenerOCrearPorNombre($nombre, 'verificacion' . bin2hex(random_bytes(6)), 'Manual: comprobacion');
        if ( ! $copia->tieneComponentes()) {throw new RuntimeException('New manual did not inherit its catalog preset');}
        $idu_destino = 'verificacion' . bin2hex(random_bytes(6));
        (new Plantillas_reglas)->duplicar($p->idu, $idu_destino);
        if (count($componentes->reglas($idu_destino)) !== count($antes)) {
            throw new RuntimeException('Incomplete duplicate: ' . $nombre);
        }
        $regla = $antes[0];
        $componentes->guardarValor($p->idu, $regla->idu, $regla->valor);
        try {
            $componentes->guardarValor($p->idu, $regla->idu, 'red;} body {color:red');
            throw new RuntimeException('Invalid declaration accepted');
        } catch (InvalidArgumentException $e) {
            // Expected: declarations cannot escape their stored selector.
        }
        $resultados[$nombre] = count($antes);
    }
} finally {
    $db->rollBack();
}
echo json_encode(['ordinary_templates_unchanged' => $iguales, 'decorations_preserved' => $resultados, 'duplicate' => 'ok', 'controls' => 'ok', 'invalid_css' => 'rejected']), "\n";
