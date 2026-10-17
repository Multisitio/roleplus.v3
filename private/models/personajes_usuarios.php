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
        if ( ! Session::get('idu')) { throw new RuntimeException('Sesión ausente.', 401); }
        parent::query('START TRANSACTION');
        try {
            $estado = parent::first('SELECT * FROM personajes_estado WHERE idu=? FOR UPDATE', [$personajes_idu]);
            if ( ! $estado || $estado->eliminado || $estado->fichas_idu !== $fichas_idu) { throw new RuntimeException('Personaje inexistente.', 404); }
            $vinculo = parent::first('SELECT usuarios_idu FROM personajes_usuarios WHERE personajes_idu=?', [$personajes_idu]);
            if ($vinculo) { throw new RuntimeException('Este personaje ya está vinculado. Debe desvincularse antes.', 409); }
            parent::query('INSERT INTO personajes_usuarios (fichas_idu,personajes_idu,usuarios_idu) VALUES (?,?,?)', [$fichas_idu,$personajes_idu,Session::get('idu')]);
            parent::query('UPDATE personajes_estado SET usuarios_idu=?,revision=revision+1 WHERE idu=?', [Session::get('idu'),$personajes_idu]);
            parent::query('UPDATE personajes SET usuarios_idu=? WHERE idu=?', [Session::get('idu'),$personajes_idu]);
            parent::query('COMMIT');
        } catch (Throwable $e) { parent::query('ROLLBACK'); throw $e; }
    }

    #
    public function desvincular($personajes_idu)
    {
        if ( ! Session::get('idu')) { throw new RuntimeException('Sesión ausente.', 401); }
        parent::query('START TRANSACTION');
        try {
            parent::first('SELECT idu FROM personajes_estado WHERE idu=? FOR UPDATE', [$personajes_idu]);
            parent::query('DELETE FROM personajes_usuarios WHERE personajes_idu=? AND usuarios_idu=?', [$personajes_idu,Session::get('idu')]);
            parent::query('UPDATE personajes_estado SET revision=revision+1 WHERE idu=?', [$personajes_idu]);
            parent::query('COMMIT');
        } catch (Throwable $e) { parent::query('ROLLBACK'); throw $e; }
    }
}
