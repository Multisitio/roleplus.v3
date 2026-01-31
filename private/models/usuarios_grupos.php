<?php
/**
 */
class Usuarios_grupos extends LiteRecord
{
    # R+2
    public function unirse($grupos_idu)
    {
        $values[] = Session::get('idu');
        $values[] = $grupos_idu;

		$sql = 'SELECT * FROM usuarios_grupos WHERE usuarios_idu=? AND grupos_idu=?';
        if ( self::first($sql, $values) ) {
            return Session::setArray('toast', t('¡Ya estás unido!'));
        }

        $sql = 'INSERT INTO usuarios_grupos SET usuarios_idu=?, grupos_idu=?';
        self::query($sql, $values);
        Session::setArray('toast', t('¡Bienvenido al grupo!'));

        (new Grupos)->actualizarContador($grupos_idu);
    }

    # R+2
    public function contar($grupos_idu)
    {
        $sql = 'SELECT COUNT(id) as miembros FROM usuarios_grupos WHERE grupos_idu=?';
        return self::first($sql, [$grupos_idu])->miembros;
    }

    # R+2
    public function dejar($grupos_idu, $usuarios_idu='')
    {
        $values[] = empty($usuarios_idu) ? Session::get('idu') : $usuarios_idu;
        $values[] = $grupos_idu;

        $sql = 'DELETE FROM usuarios_grupos WHERE usuarios_idu=? AND grupos_idu=?';
        self::query($sql, $values);

        (new Grupos)->actualizarContador($grupos_idu);
    }

    #
    public function todos()
    {
        $values[] = Session::get('idu');
        $sql = 'SELECT grupos_idu FROM usuarios_grupos WHERE usuarios_idu=?';
        $grupos = self::query($sql, $values);
        $a = [];
        foreach ($grupos as $o) {
            $a[$o->grupos_idu] = 1;
        }
        return $a;
    }

    # R+2
    public function enGrupo($grupos_idu)
    {
        $sql = 'SELECT usu.apodo, usu.hashtag, usu.avatar, usu.eslogan, usu.idu, usu.rol
            FROM usuarios_grupos u_g, usuarios usu
            WHERE u_g.grupos_idu=?
            AND u_g.usuarios_idu=usu.idu';
        $usuarios = self::all($sql, [$grupos_idu]);
        return self::arrayBy($usuarios);
    }

    # R+2
    public function enGruposPorHashtags(array $hashtags)
    {
        if ( ! $hashtags) {
            return [];
        }
        
        $sql = 'SELECT usu.apodo, usu.hashtag, usu.avatar, usu.eslogan, usu.idu, usu.rol
            FROM usuarios_grupos u_g, usuarios usu, grupos gru
            WHERE u_g.usuarios_idu=usu.idu
            AND gru.idu=u_g.grupos_idu';

        foreach ($hashtags as $hashtag) {
            $sql .= ' AND gru.hashtag=?';
            $values[] = str_replace('#', '', $hashtag);
        }

        $usuarios = self::all($sql, $values);
        return self::arrayBy($usuarios);
    }
}
