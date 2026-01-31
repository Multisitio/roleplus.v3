<?php
/**
 */
class Acciones extends LiteRecord
{
    # Alternar acción
    public function alternar($idu, $elemento, $accion)
    {
        $hay = $this->registro($idu, $elemento, $accion);
        $hay ? self::delete($hay->id) : $this->crear($elemento, $idu, $accion);
        return $hay ? false : true;
    }

    # Crear acción
    public function crear($elemento, $idu, $accion)
    {
        $sql = 'INSERT INTO acciones SET usuarios_idu=?, elemento=?, idu=?, accion=?';
        self::query($sql, [Session::get('idu'), $elemento, $idu, $accion]);
        return $this->registro($idu, $elemento, $accion);
    }

    # Uno por idu
    public function registro($idu, $elemento, $accion)
    {
        $sql = 'SELECT * FROM acciones WHERE usuarios_idu=? AND idu=? AND elemento=? AND accion=?';
        return self::first($sql, [Session::get('idu'), $idu, $elemento, $accion]);
    }

    # Registros por usuario y acción con los idu por clave
    public function registros($accion)
    {
        $sql = 'SELECT * FROM acciones WHERE usuarios_idu=? AND accion=?';
        $registros = self::all($sql, [Session::get('idu'), $accion]);
        $arr = [];
        foreach ($registros as $obj) {
            $arr[$obj->idu] = $obj;
        }
        return $arr;
    }

    # Registros por el idu, elemento y accion con los idu de usuario por clave
    public function registrosPorIdu($idu, $elemento, $accion)
    {
        $sql = 'SELECT * FROM acciones WHERE idu=? AND elemento=? AND accion=?';
        $registros = self::all($sql, [$idu, $elemento, $accion]);
        $arr = [];
        foreach ($registros as $obj) {
            $arr[$obj->usuarios_idu] = $obj;
        }
        return $arr;
    }

    #
    public function registrosPorElementoYAccion($elemento, $accion)
    {
        $sql = 'SELECT * FROM acciones WHERE usuarios_idu=? AND elemento=? AND accion=?';
        $registros = self::all($sql, [Session::get('idu'), $elemento, $accion]);
        $arr = [];
        foreach ($registros as $obj) {
            $arr[$obj->idu] = $obj;
        }
        return $arr;
    }
}
