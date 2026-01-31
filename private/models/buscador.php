<?php
/**
 */
class Buscador extends LiteRecord
{
	#
	public function registrar($frase)
	{
		$sql = 'SELECT id, cuanto FROM buscador WHERE quien=? AND que=?';
        $registro = self::first($sql, [Session::get('idu'), $frase]);
        if ($registro) {
            $sql = 'UPDATE buscador SET hasta=?, cuanto=? WHERE id=?';
            return self::query($sql, [_date::format(), ++$registro->cuanto, $registro->id]);
        }
        $sql = 'INSERT INTO buscador SET quien=?, que=?, desde=?';
        self::query($sql, [Session::get('idu'), $frase, _date::format()]);
	}

	#
    public function grupos($frase)
    {
        $sql = 'SELECT *
            FROM grupos
            WHERE nombre LIKE ?
            OR hashtag LIKE ?
            OR eslogan LIKE ?
            OR info LIKE ?
            ORDER BY ultima_pub DESC
            LIMIT 25';
        return (new Grupos)->all($sql, ["%$frase%", "%$frase%", "%$frase%", "%$frase%"]);
    }

	#
    public function publicaciones($frase)
    {
        $sql = 'SELECT pub.*, usu.apodo, usu.hashtag, usu.avatar, usu.email, usu.rol, usu.socio, usu.ultima_pub
            FROM publicaciones pub, usuarios usu
            WHERE pub.usuarios_idu=usu.idu AND
            (
                pub.usuarios_idu=?
                OR pub.usuarios_hashtag=?
                OR pub.tipo=?
                OR pub.subtipo=?
                OR pub.etiquetas LIKE ?
                OR pub.titulo LIKE ?
                OR pub.contenido LIKE ?
                OR pub.idu=?
                OR pub.evento_desde LIKE ?
                OR pub.publicado LIKE ?
            )
            ORDER BY pub.publicado DESC
            LIMIT 25';
        return (new Publicaciones)->all($sql, [$frase, $frase, $frase, $frase, "%$frase%", "%$frase%", "%$frase%", $frase, "$frase%", "$frase%"]);
    }

	#
    public function usuarios($frase)
    {
        $sql = 'SELECT idu,apodo,hashtag,atentos,avatar,email,eslogan,experiencia,rol,socio,cuota,ultimo_pago,sobre_mi,ultima_pub,tocado
            FROM usuarios
            WHERE email=?
            OR idu=?
            OR apodo LIKE ?
            OR hashtag LIKE ?
            OR eslogan LIKE ?
            OR sobre_mi LIKE ?
            OR socio LIKE ?
            OR ip=?
            ORDER BY tocado DESC
            LIMIT 25';
        return (new Usuarios)->all($sql, [$frase, $frase, "%$frase%", "%$frase%", "%$frase%", "%$frase%", "$frase%", $frase]);
    }
}
