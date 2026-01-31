<?php
#
class Fichas_cajas extends LiteRecord
{
    #
    public function copiarCajas($fichas_idu_desde, $fichas_idu_a)
    {
        $sql = 'SELECT * FROM fichas_cajas WHERE fichas_idu=?';
        $cajas = self::all($sql, [$fichas_idu_desde]);

        $sql = "DELETE FROM fichas_cajas WHERE usuarios_idu=? AND fichas_idu=?";
        self::query($sql, [Session::get('idu'), $fichas_idu_a]);

        $sql = "INSERT INTO fichas_cajas (usuarios_idu, fichas_idu, peso, nombre, idu, padre_idu, tipo, subtipo, borde, variable_nombre, variable_marcador, variable_valor, texto, notas, imagenes, tam_s, tam_m, tam_l, tam_xl, linea_nueva, alto, margen, sangria, alineado) VALUES";

        foreach ($cajas as $caj)
        {
            $sql .= " (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?),";
            $values[] = Session::get('idu');
            $values[] = $fichas_idu_a;
            $values[] = $caj->peso;
            $values[] = $caj->nombre;
            $values[] = $caj->idu;
            $values[] = $caj->padre_idu;
            $values[] = $caj->tipo;
            $values[] = $caj->subtipo;
            $values[] = $caj->borde;
            $values[] = $caj->variable_nombre;
            $values[] = $caj->variable_marcador;
            $values[] = $caj->variable_valor;
            $values[] = $caj->texto;
            $values[] = $caj->notas;
            $values[] = $caj->imagenes;
            $values[] = $caj->tam_s;
            $values[] = $caj->tam_m;
            $values[] = $caj->tam_l;
            $values[] = $caj->tam_xl;
            $values[] = $caj->linea_nueva;
            $values[] = $caj->alto;
            $values[] = $caj->margen;
            $values[] = $caj->sangria;
            $values[] = $caj->alineado;
        }
        $sql = rtrim($sql, ',');

        if (empty($values)) {
            return;
        }
        self::query($sql, $values);
        Session::setArray('toast', t('Cajas copiadas.'));
    }

    #
	private static function verSiExiste($fichas_idu, $variable_nombre)
	{
		$sql = "SELECT * FROM fichas_cajas WHERE fichas_idu=? and variable_nombre=?";
        $rows = self::all($sql, [$fichas_idu, $variable_nombre]);
        return (count($rows)>1) ? true : false;
    }

    #
	private static function validar($cat)
	{
		$sql = 'SELECT id FROM fichas WHERE usuarios_idu=? AND idu=?';
        if ( ! self::first($sql, [Session::get('idu'), $cat['fichas_idu']])) {
		    return Session::setArray('toast', t('No puedes editar una ficha de la que no eres propietario.'));
        }

        $cat['variable_nombre'] = preg_replace('/[^\w]/', '_', $cat['variable_nombre']);
        if ($cat['variable_nombre'] && self::verSiExiste($cat['fichas_idu'], $cat['variable_nombre'])) {
            Session::setArray('toast', t('Las variables deben tener nombres únicos.'));
        }

        $cat['imagenes_subidas'] = empty($_FILES['imagenes']['name'][0])
            ? $cat['imagenes_subidas'] : (new Archivos)->incluir($_FILES);

        $cat['peso'] = empty($cat['peso']) ? 0 : (string)$cat['peso'];

        $cat['nombre'] = trim($cat['nombre']);

        $cat['borde'] = empty($cat['borde']) ? 0 : 1;

        $cat['alineado'] = preg_match('/left|center|right/i', $cat['alineado']) ? $cat['alineado'] : 'left';

        return $cat;
    }

    #
	public function actualizar($cat)
	{
        $cat = self::validar($cat);
        if ( ! is_array($cat)) {
            return;
        }

		$values[] = (string)$cat['peso'];
        $values[] = (string)$cat['nombre'];
        $values[] = (string)$cat['padre_idu'];
		$values[] = (string)$cat['tipo'];
		$values[] = h($cat['subtipo']);
		$values[] = (int)$cat['borde'];
        $values[] = (string)$cat['variable_nombre'];
		$values[] = (string)$cat['variable_marcador'];
		$values[] = (string)$cat['variable_valor'];
		$values[] = (string)$cat['texto'];
		$values[] = (string)$cat['notas'];
		$values[] = (string)$cat['imagenes_subidas'];
		$values[] = (string)$cat['tam_s'];
		$values[] = (string)$cat['tam_m'];
		$values[] = (string)$cat['tam_l'];
		$values[] = (string)$cat['tam_xl'];
		$values[] = empty($cat['linea_nueva']) ? 0 : 1;
		$values[] = (string)$cat['alto'];
		$values[] = (string)$cat['margen'];
		$values[] = (string)$cat['sangria'];
		$values[] = (string)$cat['alineado'];
        $values[] = Session::get('idu');
		$values[] = (string)$cat['idu'];

		$sql = 'UPDATE fichas_cajas SET peso=?, nombre=?, padre_idu=?, tipo=?, subtipo=?, borde=?, variable_nombre=?, variable_marcador=?, variable_valor=?, texto=?, notas=?, imagenes=?, tam_s=?, tam_m=?, tam_l=?, tam_xl=?, linea_nueva=?, alto=?, margen=?, sangria=?, alineado=? WHERE usuarios_idu=? AND idu=?';
        if (self::query($sql, $values)) {
		    Session::setArray('toast', t('Caja actualizada.'));
        }
    }

    #
	public function crear($cat)
	{
        $cat = self::validar($cat);
        if ( ! is_array($cat)) {
            return;
        }

        $values[] = Session::get('idu');
        $values[] = (string)$cat['fichas_idu'];
		$values[] = (string)$cat['peso'];
        $values[] = (string)$cat['nombre'];
		$values[] = $idu = _str::uid($cat['nombre']);
        $values[] = (string)$cat['padre_idu'];
		$values[] = (string)$cat['tipo'];
		$values[] = h($cat['subtipo']);
		$values[] = (int)$cat['borde'];
        $values[] = (string)$cat['variable_nombre'];
		$values[] = (string)$cat['variable_marcador'];
		$values[] = (string)$cat['variable_valor'];
		$values[] = (string)$cat['texto'];
		$values[] = (string)$cat['notas'];
		$values[] = (string)$cat['imagenes_subidas'];
		$values[] = (string)$cat['tam_s'];
		$values[] = (string)$cat['tam_m'];
		$values[] = (string)$cat['tam_l'];
		$values[] = (string)$cat['tam_xl'];
		$values[] = empty($cat['linea_nueva']) ? 0 : 1;
		$values[] = (string)$cat['alto'];
		$values[] = (string)$cat['margen'];
		$values[] = (string)$cat['sangria'];
		$values[] = (string)$cat['alineado'];

		$sql = 'INSERT INTO fichas_cajas SET usuarios_idu=?, fichas_idu=?, peso=?, nombre=?, idu=?, padre_idu=?, tipo=?, subtipo=?, borde=?, variable_nombre=?, variable_marcador=?, variable_valor=?, texto=?, notas=?, imagenes=?, tam_s=?, tam_m=?, tam_l=?, tam_xl=?, linea_nueva=?, alto=?, margen=?, sangria=?, alineado=?';
        if (self::query($sql, $values)) {
		    Session::setArray('toast', t('Caja creada.'));
        }
		return $idu;
    }

    public function eliminar($cajas_idu)
    {
        $sql = 'DELETE FROM fichas_cajas WHERE usuarios_idu=? AND idu=?';
        if (self::query($sql, [Session::get('idu'), $cajas_idu])) {
            Session::setArray('toast', t('Caja eliminada.'));
        }
    }

    public function todas($fichas_idu)
    {
        $sql = 'SELECT * FROM fichas_cajas WHERE fichas_idu=? ORDER BY peso';
        return self::all($sql, [$fichas_idu]);
    }

    public function todasPadres($fichas_idu)
    {
        $sql = "SELECT * FROM fichas_cajas WHERE fichas_idu=? AND tipo='division' ORDER BY peso";
        return self::all($sql, [$fichas_idu]);
    }

    public function variables($fichas_idu)
    {
        $sql = "SELECT variable_nombre FROM fichas_cajas WHERE fichas_idu=? AND variable_nombre<>'' ORDER BY variable_nombre";
        $cat = self::all($sql, [$fichas_idu]);
        foreach ($cat as $obj) {
            $variables[$obj->variable_nombre] = '';
        }  
        return $variables;
    }

    public function una($idu)
    {
		$sql = 'SELECT * FROM fichas_cajas WHERE idu=?';
        $ficha = self::first($sql, [$idu]);
        return empty($ficha) ? self::cols() : $ficha;
    }
}
