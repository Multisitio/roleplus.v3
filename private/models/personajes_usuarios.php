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
        $usuarios_idu = Session::get('idu');

        # 1. Transferimos Fichas
        $sql = "UPDATE fichas SET usuarios_idu=? WHERE idu=?";
        parent::query($sql, [$usuarios_idu, $fichas_idu]);

        # 2. Transferimos Cajas de la ficha
        $sql = "UPDATE fichas_cajas SET usuarios_idu=? WHERE fichas_idu=?";
        parent::query($sql, [$usuarios_idu, $fichas_idu]);

        # 3. Transferimos Personajes (datos)
        $sql = "UPDATE personajes SET usuarios_idu=? WHERE idu=?";
        parent::query($sql, [$usuarios_idu, $personajes_idu]);

        # 4. Eliminamos vinculaciones previas y creamos la nuestra
        $sql = "DELETE FROM personajes_usuarios WHERE personajes_idu=?";
        parent::query($sql, [$personajes_idu]);

        $sql = "INSERT INTO personajes_usuarios SET fichas_idu=?, personajes_idu=?, usuarios_idu=?";
        parent::query($sql, [$fichas_idu, $personajes_idu, $usuarios_idu]);
    }

    #
    public function desvincular($personajes_idu)
    {
        $sql = "DELETE FROM personajes_usuarios WHERE personajes_idu=? AND usuarios_idu=?";
        parent::query($sql, [$personajes_idu, Session::get('idu')]);
    }
}
