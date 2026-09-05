<?php
/**
 * Modelo para las reglas de las plantillas.
 *
 * Contrato CRUD:
 * - Save crea o actualiza una variable CSS de .plantilla.
 * - Si Save recibe un IDU alfanumerico, actualiza esa fila.
 * - Si Save recibe valor vacio, borra la variable.
 * - Read devuelve defaults + personalizaciones, preservando valores como 0,
 *   none y normal.
 * - Delete limpia residuos conocidos y claves obsoletas.
 */
class Plantillas_reglas extends LiteRecord
{
    const SELECTOR_VARIABLES = '.plantilla';

    const SELECTORES_OBSOLETOS = [
        '.plantilla.plantilla div article footer',
        '.plantilla.plantilla div article footer::after',
        '.plantilla.plantilla div article footer::before',
        '.plantilla.plantilla div:nth-child(even) article footer',
        '.plantilla.plantilla div:nth-child(even) article footer::after',
        '.plantilla.plantilla div:nth-child(odd) article footer',
        '.plantilla.plantilla div:nth-child(odd) article footer::after',
        '.plantilla>div',
        '.plantilla>div article',
        '.plantilla>div article small',
        '.plantilla>div footer',
        '.plantilla>div footer::after',
        '.plantilla>div footer::before',
        '.plantilla>div:nth-child(even) footer',
        '.plantilla>div:nth-child(even) footer::after',
        '.plantilla>div:nth-child(odd) footer',
        '.plantilla>div:nth-child(odd) footer::after',
        '.plantilla>div h1',
        '.plantilla>div h2',
        '.plantilla>div h3',
        '.plantilla>div h4',
        '.plantilla>div h5',
        '.plantilla>div h6',
    ];

    const PROPIEDADES_OBSOLETAS = [
        '--list-padding',
    ];

    public function guardar($plantillas_idu, $propiedad, $valor, $reglas_idu = '')
    {
        $plantillas_idu = trim((string)$plantillas_idu);
        $propiedad = mb_strtolower(trim((string)$propiedad), 'UTF-8');
        $valor = trim((string)$valor);
        $reglas_idu = trim((string)$reglas_idu);

        if (!$plantillas_idu || !str_starts_with($propiedad, '--')) {
            return null;
        }

        // Clean up any other obsolete selectors / rules first
        $this->eliminar_obsoletas($plantillas_idu);

        // Delete contract
        if ($valor === '') {
            if ($this->es_idu($reglas_idu)) {
                self::query(
                    'DELETE FROM plantillas_reglas WHERE plantillas_idu=? AND idu=?',
                    [$plantillas_idu, $reglas_idu]
                );
            } else {
                $this->eliminar_var($plantillas_idu, $propiedad);
            }
            return null;
        }

        // Update if rules_idu is specified and valid
        if ($this->es_idu($reglas_idu)) {
            // Delete all other rules for the same property to prevent conflict / desfasadas
            self::query(
                'DELETE FROM plantillas_reglas WHERE plantillas_idu=? AND propiedad=? AND idu != ?',
                [$plantillas_idu, $propiedad, $reglas_idu]
            );

            self::query(
                'UPDATE plantillas_reglas SET selector=?, propiedad=?, valor=? WHERE plantillas_idu=? AND idu=?',
                [self::SELECTOR_VARIABLES, $propiedad, $valor, $plantillas_idu, $reglas_idu]
            );

            $updated = self::first(
                'SELECT idu FROM plantillas_reglas WHERE plantillas_idu=? AND idu=?',
                [$plantillas_idu, $reglas_idu]
            );
            if ($updated) {
                return $updated->idu;
            }
        }

        // Otherwise, check if we already have any rule for this property (under selector or obsolete selectors)
        $existing = self::all(
            'SELECT idu FROM plantillas_reglas WHERE plantillas_idu=? AND propiedad=?',
            [$plantillas_idu, $propiedad]
        );

        if ($existing) {
            // Keep the first one and delete the rest
            $keep_idu = $existing[0]->idu;
            if (count($existing) > 1) {
                $ids_to_delete = [];
                for ($i = 1; $i < count($existing); $i++) {
                    $ids_to_delete[] = $existing[$i]->idu;
                }
                $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
                self::query(
                    "DELETE FROM plantillas_reglas WHERE plantillas_idu=? AND idu IN ($placeholders)",
                    array_merge([$plantillas_idu], $ids_to_delete)
                );
            }

            // Update the single kept rule
            self::query(
                'UPDATE plantillas_reglas SET selector=?, valor=? WHERE plantillas_idu=? AND idu=?',
                [self::SELECTOR_VARIABLES, $valor, $plantillas_idu, $keep_idu]
            );

            return $keep_idu;
        }

        // Create new
        $idu = _str::uid('reg');
        self::query(
            'INSERT INTO plantillas_reglas SET idu=?, plantillas_idu=?, selector=?, propiedad=?, valor=?, peso=0',
            [$idu, $plantillas_idu, self::SELECTOR_VARIABLES, $propiedad, $valor]
        );

        return $idu;
    }

    public function leer($plantillas_idu, array $defaults = [])
    {
        $rows = self::all(
            'SELECT propiedad, valor FROM plantillas_reglas WHERE plantillas_idu=? AND selector=?',
            [$plantillas_idu, self::SELECTOR_VARIABLES]
        );

        $vars = $defaults;
        foreach ($rows as $r) {
            $propiedad = mb_strtolower(trim((string)$r->propiedad), 'UTF-8');
            if (str_starts_with($propiedad, '--')) {
                $vars[$propiedad] = trim((string)$r->valor);
            }
        }

        return $vars;
    }

    public function eliminar_var($plantillas_idu, $propiedad)
    {
        $propiedad = mb_strtolower(trim((string)$propiedad), 'UTF-8');
        if (!str_starts_with($propiedad, '--')) return;

        self::query(
            'DELETE FROM plantillas_reglas WHERE plantillas_idu=? AND selector=? AND propiedad=?',
            [$plantillas_idu, self::SELECTOR_VARIABLES, $propiedad]
        );
    }

    public function eliminar_plantilla($plantillas_idu)
    {
        self::query('DELETE FROM plantillas_reglas WHERE plantillas_idu=?', [$plantillas_idu]);
    }

    public function eliminar_obsoletas($plantillas_idu, array $propiedades_vigentes = [])
    {
        if (!$plantillas_idu) return;

        $this->sanear($plantillas_idu);

        if (self::PROPIEDADES_OBSOLETAS) {
            $placeholders = implode(',', array_fill(0, count(self::PROPIEDADES_OBSOLETAS), '?'));
            self::query(
                "DELETE FROM plantillas_reglas WHERE plantillas_idu=? AND selector=? AND propiedad IN ($placeholders)",
                array_merge([$plantillas_idu, self::SELECTOR_VARIABLES], self::PROPIEDADES_OBSOLETAS)
            );
        }

        if ($propiedades_vigentes) {
            $propiedades_vigentes = array_values(array_unique(array_filter(array_map(function ($p) {
                $p = mb_strtolower(trim((string)$p), 'UTF-8');
                return str_starts_with($p, '--') ? $p : null;
            }, $propiedades_vigentes))));

            if ($propiedades_vigentes) {
                $placeholders = implode(',', array_fill(0, count($propiedades_vigentes), '?'));
                self::query(
                    "DELETE FROM plantillas_reglas WHERE plantillas_idu=? AND selector=? AND propiedad NOT IN ($placeholders)",
                    array_merge([$plantillas_idu, self::SELECTOR_VARIABLES], $propiedades_vigentes)
                );
            }
        }
    }

    public function sanear($plantillas_idu)
    {
        if (!$plantillas_idu) return;

        $placeholders = implode(',', array_fill(0, count(self::SELECTORES_OBSOLETOS), '?'));
        self::query(
            "DELETE FROM plantillas_reglas WHERE plantillas_idu=? AND selector IN ($placeholders)",
            array_merge([$plantillas_idu], self::SELECTORES_OBSOLETOS)
        );

        self::query(
            "DELETE FROM plantillas_reglas WHERE plantillas_idu=? AND selector LIKE 'aside.template%'",
            [$plantillas_idu]
        );

        self::query(
            'DELETE FROM plantillas_reglas WHERE plantillas_idu=? AND selector=? AND propiedad NOT LIKE ?',
            [$plantillas_idu, self::SELECTOR_VARIABLES, '--%']
        );

        self::query(
            'DELETE FROM plantillas_reglas WHERE plantillas_idu=? AND selector != ?',
            [$plantillas_idu, self::SELECTOR_VARIABLES]
        );
    }

    public function guardar_una($plantillas_idu, $selector, $propiedad, $valor)
    {
        if (trim((string)$selector) !== self::SELECTOR_VARIABLES) return null;
        return $this->guardar($plantillas_idu, $propiedad, $valor);
    }

    public function duplicar($idu_origen, $idu_destino)
    {
        $reglas = self::all(
            'SELECT propiedad, valor FROM plantillas_reglas WHERE plantillas_idu=? AND selector=?',
            [$idu_origen, self::SELECTOR_VARIABLES]
        );

        foreach ($reglas as $r) {
            $this->guardar($idu_destino, $r->propiedad, $r->valor);
        }
    }

    private function es_idu($idu)
    {
        return (bool)preg_match('/^[a-z0-9]+$/i', (string)$idu);
    }
}
