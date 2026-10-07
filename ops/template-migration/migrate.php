<?php
/** CLI only. Explicit environment bootstrap, transaction and mandatory backup. */
if (PHP_SAPI !== 'cli' || count($argv) < 3) {
    exit("Usage: php migrate.php bootstrap.php backup.json\n");
}
require $argv[1];
function valorComparable($valor)
{
    $valor = strtolower(trim((string)$valor));
    if (preg_match('/^(-?\d+(?:\.\d+)?)(?:px)?$/', $valor, $m)) {
        return (string)(float)$m[1];
    }
    return strtr($valor, ['#ffffff' => 'white', '#fff' => 'white', '#000000' => 'black', '#000' => 'black']);
}
$defaults = Plantillas::DEFAULTS;
foreach (Plantillas::NIVELES_TIPOGRAFIA as $nivel) {
    $defaults['--' . $nivel . '-weight'] = $defaults['--' . $nivel . '-weight'] ?? 'normal';
}
$db = Kumbia\ActiveRecord\Db::get();
$nombres = ['srd20', 'for_the_quest', 'dragonbane'];
$rows = $db->query("SELECT * FROM plantillas WHERE nombre IN ('srd20','for_the_quest','dragonbane')")->fetchAll(PDO::FETCH_ASSOC);
$reglas = [];
$read = $db->prepare('SELECT * FROM plantillas_reglas WHERE plantillas_idu=? ORDER BY id');
foreach ($rows as $row) {
    $read->execute([$row['idu']]);
    $reglas[$row['idu']] = $read->fetchAll(PDO::FETCH_ASSOC);
}
if (file_exists($argv[2]) || file_put_contents($argv[2], json_encode(['plantillas' => $rows, 'reglas' => $reglas], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false) {
    exit("Backup must be a new writable file. No changes made.\n");
}
chmod($argv[2], 0600);
$migradas = [];
$db->beginTransaction();
try {
    foreach ($nombres as $nombre) {
        $seed = json_decode(file_get_contents(__DIR__ . '/' . $nombre . '.json'), true, 512, JSON_THROW_ON_ERROR);
        $catalogo = $db->prepare('SELECT idu FROM plantillas WHERE nombre=? AND usuarios_idu=?');
        $catalogo->execute([$nombre, 'catalogo']);
        if ( ! $catalogo->fetchColumn()) {
            $idu = _str::uid('plt');
            $q = $db->prepare('INSERT INTO plantillas (idu,usuarios_idu,nombre) VALUES (?,?,?)');
            $q->execute([$idu, 'catalogo', $nombre]);
            $rows[] = ['idu' => $idu, 'nombre' => $nombre];
        }
        foreach ($rows as $row) {
            if ($row['nombre'] !== $nombre) {
                continue;
            }
            $idu = $row['idu'];
            if ((new Plantillas_reglas)->tieneComponentes($idu)) {
                continue;
            }
            $existentes = [];
            foreach ($reglas[$idu] ?? [] as $regla) {
                if ($regla['selector'] === '.plantilla' && str_starts_with($regla['propiedad'], '--')) {
                    $existentes[$regla['propiedad']] = $regla['valor'];
                }
            }
            $variables = array_replace(Plantillas::DEFAULTS, $seed['variables'], $seed['ajustes']);
            foreach ($variables as $propiedad => $valor) {
                if (array_key_exists($propiedad, Plantillas::DEFAULTS) || array_key_exists($propiedad, $seed['ajustes']) && ! str_starts_with($propiedad, '--componentes-')) {
                    $variables['--componentes-base-' . substr($propiedad, 2)] = $valor;
                }
            }
            foreach ($existentes as $propiedad => $valor) {
                // Keep genuine customizations; blank-template defaults are not legacy styles.
                if ( ! array_key_exists($propiedad, $defaults) || valorComparable($valor) !== valorComparable($defaults[$propiedad])) {
                    $variables[$propiedad] = $valor;
                }
            }
            $delete = $db->prepare('DELETE FROM plantillas_reglas WHERE plantillas_idu=? AND selector=?');
            $delete->execute([$idu, '.plantilla']);
            $nuevas = [];
            foreach ($seed['reglas'] as $regla) {
                foreach ($regla['declarations'] as [$propiedad, $valor]) {
                    $nuevas[] = [bin2hex(random_bytes(6)), $idu, $regla['selector'], $propiedad, $valor, $regla['peso']];
                }
            }
            $variables['--componentes-version'] = '1';
            foreach ($variables as $propiedad => $valor) {
                $nuevas[] = [bin2hex(random_bytes(6)), $idu, '.plantilla', $propiedad, $valor, 0];
            }
            foreach (array_chunk($nuevas, 100) as $lote) {
                $sql = 'INSERT INTO plantillas_reglas (idu,plantillas_idu,selector,propiedad,valor,peso) VALUES '
                    . implode(',', array_fill(0, count($lote), '(?,?,?,?,?,?)'));
                $db->prepare($sql)->execute(array_merge(...$lote));
            }
            $migradas[] = $idu;
        }
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}
foreach ($migradas as $idu) {
    $p = (new Plantillas)->first('SELECT * FROM plantillas WHERE idu=?', [$idu]);
    if ( ! $p->compilar() || $p->getCssUrl() === '') {
        throw new RuntimeException('Compilation failed for ' . $idu);
    }
}
echo json_encode(['migradas' => $migradas, 'cantidad' => count($migradas)], JSON_UNESCAPED_UNICODE), "\n";
