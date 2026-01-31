<?php
#
class Mapa_elementos extends LiteRecord
{
    # R+2
    public function actualizar($post)
    {
        $sql = 'UPDATE mapa_elementos SET color=?, contenido=?, lat=?, lng=? WHERE idu=? AND usuarios_idu=?';
        self::query($sql, [$post['color'], $post['contenido'], $post['lat'], $post['lng'], $post['idu'], Session::get('idu')]);
        return $this->uno($post['idu']);
    }

    # R+2
    public function crear($post)
    {
        $sql = 'INSERT INTO mapa_elementos SET usuarios_idu=?, grupos_idu=?, idu=?, color=?, contenido=?, lat=?, lng=?';
        $idu = _str::uid();
        self::query($sql, [Session::get('idu'), $post['grupos_idu'], $idu, $post['color'], $post['contenido'], $post['lat'], $post['lng']]);
        return $this->uno($idu);
    }

    # R+2
    public function eliminar($idu)
    {
        $sql = 'DELETE FROM mapa_elementos WHERE idu=? AND usuarios_idu=?';
        self::query($sql, [$idu, Session::get('idu')]);
    }

    # R+2
    public function salvar($post)
    {        
        return empty($post['idu']) ? $this->crear($post) : $this->actualizar($post);
    }

    # R+2
    public function todos($grupos_idu)
    {
        $sql = 'SELECT * FROM mapa_elementos WHERE grupos_idu=?';
        return self::all($sql, [$grupos_idu]);
    }

    # R+2
    public function uno($idu)
    {
        $sql = 'SELECT * FROM mapa_elementos WHERE idu=?';
        return self::first($sql, [$idu]);
    }
}
