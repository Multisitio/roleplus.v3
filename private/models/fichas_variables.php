<?php
#
class Fichas_variables extends LiteRecord
{
    #
    public function clonarVariables($fichas_idu_desde, $fichas_idu_a)
    {
        $sql = 'SELECT * FROM fichas_variables WHERE fichas_idu=?';
        $variables = self::all($sql, $fichas_idu_desde);

        $sql = "DELETE FROM fichas_variables WHERE usuarios_idu=? AND fichas_idu=?";
        self::query($sql, [Session::get('idu'), $fichas_idu_a]);

        $sql = "INSERT INTO fichas_variables (usuarios_idu, fichas_idu, variable, formula, atributos) VALUES";

        foreach ($variables as $var)
        {
            $sql .= " (?, ?, ?, ?, ?),";
            $values[] = Session::get('idu');
            $values[] = $fichas_idu_a;
            $values[] = $var->variable;
            $values[] = $var->formula;
            $values[] = $var->atributos;
        }
        $sql = rtrim($sql, ',');

        if (empty($values)) {
            return;
        }
        self::query($sql, $values);
        Session::setArray('toast', t('Variables clonadas.'));
    }

    #
    public function guardar($fichas_idu, $a)
    {
        $sql = "DELETE FROM fichas_variables WHERE usuarios_idu=? AND fichas_idu=?";
        self::query($sql, [Session::get('idu'), $fichas_idu]);

        $sql = "INSERT INTO fichas_variables (usuarios_idu, fichas_idu, variable, formula, atributos) VALUES";

        $nombres = [];
        foreach ($a as $b)
        {
            foreach ($b as $c)
            {
                if ( empty($c[0]) )
                {
                    Session::setArray('toast', t('Se ha omitido un campo sin variable.'));
                    continue;
                }
                else
                {
                    $c[0] = mb_strtolower($c[0]);
                    $c[0] = str_replace(['á','é','í','ó','ú'], ['a','e','i','o','u'], $c[0]);
                    $c[0] = preg_replace('/[^\w\d]+/', '_', $c[0]);
                }

                # Eliminamos variables duplicadas
                if (in_array($c[0], $nombres)) {
                    Session::setArray('toast', t('Campos con nombre repetido: ') . $c[0]);
                    continue;
                }
                $nombres[] = $c[0];

                $sql .= " (?, ?, ?, ?, ?),";
                $values[] = Session::get('idu');
                $values[] = $fichas_idu;
                $values[] = $c[0];
                $values[] = $c[1];
                $values[] = str_ireplace(' position: relative;', '', $c[2]);
            }
        }
        $sql = rtrim($sql, ',');
        #_var::die([$a, $sql, $values]);

        if (empty($values)) {
            return Session::setArray('toast', t('Nada que guardar.'));
        }

        self::query($sql, $values);

        Session::setArray('toast', t('Campos con variables guardados.'));
    }

    public function todas($fichas_idu)
    {
        $sql = 'SELECT * FROM fichas_variables WHERE fichas_idu=?';
        $variables = self::all($sql, [$fichas_idu]);
        $a = [];
        foreach ($variables as $o) {
            $a[$o->id] = $o;
        }
        return $a;
    }
}
