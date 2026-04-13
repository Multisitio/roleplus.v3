<?php
#
class Personajes_usuarios extends LiteRecord
{
    #
    public function todos($fichas_idu)
    {
        $sql = "SELECT * FROM personajes_usuarios WHERE fichas_idu=?";
        $rows = self::all($sql, [$fichas_idu]);
        return parent::arrayBy($rows, 'personajes_idu');
    }

    #
    public function uno($personajes_idu)
    {
        $sql = "SELECT * FROM personajes_usuarios WHERE personajes_idu=? AND usuarios_idu=?";
        $row = parent::first($sql, [$personajes_idu, Session::get('idu')]);
        return $row;
    }

    #
    public function vincular($fichas_idu, $personajes_idu)
    {
        # Transferimos Personajes (datos)
        $sql = "UPDATE personajes SET usuarios_idu=? WHERE idu=?";
        parent::query($sql, [Session::get('idu'), $personajes_idu]);

        # Eliminamos vinculaciones previas y creamos la nuestra
        $sql = "DELETE FROM personajes_usuarios WHERE personajes_idu=?";
        parent::query($sql, [$personajes_idu]);

        $sql = "INSERT INTO personajes_usuarios SET fichas_idu=?, personajes_idu=?, usuarios_idu=?";
        parent::query($sql, [$fichas_idu, $personajes_idu, Session::get('idu')]);
    }

    #
    public function desvincular($personajes_idu)
    {
        $sql = "DELETE FROM personajes_usuarios WHERE personajes_idu=? AND usuarios_idu=?";
        parent::query($sql, [$personajes_idu, Session::get('idu')]);
    }
}
