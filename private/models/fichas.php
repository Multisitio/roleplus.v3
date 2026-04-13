<?php
#
class Fichas extends LiteRecord
{
    #
    public function actualizar($cat)
    {
        $nombre = trim($cat['nombre']);
        if (strlen($nombre) < 3) {
            return Session::setArray('toast', t('El nombre de la ficha ha de tener un mínimo de 3 caracteres.'));
        }

		$sql = 'SELECT * FROM fichas WHERE usuarios_idu<>? AND nombre=?';
        $ficha = self::first($sql, [Session::get('idu'), $nombre]);
        if ($ficha) {
            return Session::setArray('toast', 'El nombre está pillado o no es su ficha.');
        }

        $cat['fondo_ficha'] = empty($_FILES['imagenes']['name'][0])
            ? (string)$cat['fondo_ficha']
            : $r = (new Archivos)->incluir($_FILES);
#_var::die($r);

        $orientacion = ($cat['orientacion'] == 'landscape') ? 'landscape' : 'portrait';

        $sql = "UPDATE fichas SET nombre=?, fondo_ficha=?, orientacion=? WHERE usuarios_idu=? AND idu=?";
        self::query($sql, [$nombre, $cat['fondo_ficha'], $orientacion, Session::get('idu'), $cat['idu']]);
		Session::setArray('toast', t('¡Ficha actualizada!'));

        if ($cat['cajas_desde']) {
            (new Fichas_cajas)->copiarCajas($cat['cajas_desde'], $cat['fichas_idu']);
        }

        if ($cat['variables_desde']) {
            (new Fichas_variables)->clonarVariables($cat['variables_desde'], $cat['fichas_idu']);
        }
#_var::die($cat);
		return $cat['fichas_idu'];
    }

    #
	public function crear($cat)
	{
        $nombre = trim($cat['nombre']);
        if (strlen($nombre) < 3) {
            return Session::setArray('toast', t('El nombre de la ficha ha de tener un mínimo de 3 caracteres.'));
        }

		$sql = 'SELECT * FROM fichas WHERE usuarios_idu=? AND nombre=?';
        $ficha = self::first($sql, [Session::get('idu'), $nombre]);
        if ($ficha) {
            return Session::setArray('toast', 'Lo siento, ese nombre está pillado.');
        }

        $fondo_ficha = empty($_FILES['imagenes']['name'][0])
            ? (string)$cat['fondo_ficha']
            : (new Archivos)->incluir($_FILES);

        $orientacion = ($cat['orientacion'] == 'landscape') ? 'landscape' : 'portrait';

		$fichas_idu = _str::uid($nombre);
		$sql = 'INSERT INTO fichas SET usuarios_idu=?, nombre=?, idu=?, fondo_ficha=?, orientacion=?';
        self::query($sql, [Session::get('idu'), $nombre, $fichas_idu, $fondo_ficha, $orientacion]);
		Session::setArray('toast', t('¡Ficha creada!'));

        if ($cat['cajas_desde']) {
            (new Fichas_cajas)->copiarCajas($cat['cajas_desde'], $fichas_idu);
        }

        if ( ! empty($cat['variables_desde'])) {
            (new Fichas_variables)->clonarVariables($cat['variables_desde'], $fichas_idu);
        }

		return $fichas_idu;
    }

    public function eliminar($fichas_idu)
    {
        # El guardián puede borrar cualquier ficha.
        if (Session::get('rol') > 5) {
            $sql = 'DELETE FROM fichas WHERE idu=?';
            if (self::query($sql, [$fichas_idu])) {
                Session::setArray('toast', t('Eliminaste la ficha.'));
            }
            return;
        }
        # De lo contrario solo el propietario puede borrar una ficha.
        $sql = 'DELETE FROM fichas WHERE usuarios_idu=? AND idu=?';
        if (self::query($sql, [Session::get('idu'), $fichas_idu])) {
            Session::setArray('toast', t('!Eliminaste la ficha!'));
        }
    }

    public function todas()
    {
        $sql = 'SELECT * FROM fichas ORDER BY nombre';
        $fichas = self::all($sql);
        return self::arrayBy($fichas);
    }

    public function una($idu)
    {        
        # 1. Transferimos Fichas
        $sql = "UPDATE fichas SET usuarios_idu=? WHERE idu=?";
        parent::query($sql, [Session::get('idu'), $idu]);

        # 2. Transferimos Cajas de la ficha
        $sql = "UPDATE fichas_cajas SET usuarios_idu=? WHERE fichas_idu=?";
        parent::query($sql, [Session::get('idu'), $idu]);

		$sql = 'SELECT * FROM fichas WHERE idu=?';
        $ficha = self::first($sql, [$idu]);
        return empty($ficha) ? self::cols() : $ficha;
    }

    public function unoPorNombre($ficha_nombre)
    {
		$ficha_nombre = urldecode($ficha_nombre);
		$sql = 'SELECT * FROM fichas WHERE nombre=?';
        return self::first($sql, [$ficha_nombre]);
    }
}
