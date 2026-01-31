<?php
/**
 * Modelo para la tabla telegram_usuarios
 */
class TelegramUsuarios extends LiteRecord
{
    public function set($id_telegram, $data)
    {
        $sql = "SELECT * FROM telegram_usuarios WHERE id_telegram=?";
        $exists = self::first($sql, [$id_telegram]);

        if ($exists) {
            $alias = !empty($data['alias']) ? $data['alias'] : $exists->alias;
            $nombre = !empty($data['nombre']) ? $data['nombre'] : $exists->nombre;

            $sql = "UPDATE telegram_usuarios SET alias=?, nombre=?, visto=NOW() WHERE id=?";
            self::query($sql, [
                $alias,
                $nombre,
                $exists->id
            ]);
            return $exists->id;
        } else {
            $sql = "INSERT INTO telegram_usuarios SET id_telegram=?, alias=?, nombre=?, visto=NOW()";
            self::query($sql, [
                $id_telegram,
                $data['alias'] ?? '',
                $data['nombre'] ?? ''
            ]);
            $new = self::first("SELECT id FROM telegram_usuarios WHERE id_telegram=?", [$id_telegram]);
            return $new ? $new->id : null;
        }
    }

    public function getByTelegramId($id_telegram)
    {
        $sql = "SELECT * FROM telegram_usuarios WHERE id_telegram=?";
        return self::first($sql, [$id_telegram]);
    }

    public function linkUsuario($id_telegram, $usuarios_idu)
    {
        $sql = "UPDATE telegram_usuarios SET usuarios_idu=? WHERE id_telegram=?";
        self::query($sql, [$usuarios_idu, $id_telegram]);
    }

    public function getAll()
    {
        return self::all("SELECT * FROM telegram_usuarios");
    }
}
