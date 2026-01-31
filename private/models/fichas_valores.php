<?php
#
class Fichas_valores extends LiteRecord
{
    #
    public function generadas($fichas_idu)
    {
        $sql = 'SELECT * FROM fichas_valores WHERE fichas_idu=? AND (variable=? OR variable=?)';
        $valores = self::all($sql, [$fichas_idu, 'nombre', 'jugador']);
        #_var::die($valores);
        $a = [];
        foreach ($valores as $o)
        {
            if ( ! $o->generadas_idu or ! $o->valor) continue;
            $a[$o->generadas_idu][$o->variable] = $o->valor;
        }
        #_var::die($a);
        return $a;
    }

    public function borrar($generadas_idu)
    {
        $sql = 'DELETE FROM fichas_valores WHERE generadas_idu=?';
        if ( (new Fichas)->query($sql, [$generadas_idu]) )
            Session::setArray('toast', t('Persoanje borrado.'));
    }

    #
    public function guardar($a)
    {
        if ( empty($a['nombre']) ) {
            return Session::setArray('toast', t('El nombre del personaje es un requisito.'));
        }

        $generadas_idu = _str::id($a['nombre']);

        if ( ! empty($_FILES['imagenes']['name'][0]) ) {
            $a['foto'] = (new Archivos)->incluir($_FILES, "aplicaciones/fichas/$generadas_idu");
        }

        $fichas_idu = $a['fichas_idu'];
        unset($a['fichas_idu']);

        $sql = "DELETE FROM fichas_valores WHERE fichas_idu=? AND generadas_idu=?";
        self::query($sql, [$fichas_idu, $generadas_idu]);

        $sql = "INSERT INTO fichas_valores (usuarios_idu, fichas_idu, generadas_idu, variable, valor) VALUES";

        foreach ($a as $k=>$v)
        {
            $sql .= " (?, ?, ?, ?, ?),";
            $values[] = Session::get('idu');
            $values[] = $fichas_idu;
            $values[] = $generadas_idu;
            $values[] = $k;
            $values[] = $v;
        }

        $sql = rtrim($sql, ',');
        #if (Session::get('apodo') == 'Mr demonio') _var::die([$sql, $values]);
        self::query($sql, $values);

        Session::setArray('toast', t('Guardado.'));

        return ['fichas_idu'=>$fichas_idu, 'generadas_idu'=>$generadas_idu];
    }

    public function todos($generadas_idu)
    {
        $sql = 'SELECT * FROM fichas_valores WHERE generadas_idu=?';
        $valores = self::all($sql, $generadas_idu);
        $a = [];
        foreach ($valores as $o) {
            $a[$o->variable] = $o->valor;
        }
        return $a;
    }

    public function todas($generadas_idu)
    {
        $sql = 'SELECT * FROM fichas_valores WHERE generadas_idu=?';
        $valores = self::all($sql, $generadas_idu);
        $a = [];
        foreach ($valores as $o) {
            $a[$o->variable] = $o->valor;
        }
        return $a;
    }
}
