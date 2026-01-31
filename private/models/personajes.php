<?php
#
class Personajes extends LiteRecord
{
    #
	/*public function calcularValor($caja, $personaje)
	{
        if ( ! empty($personaje[$caja->variable_nombre])) {
            return $personaje[$caja->variable_nombre];
        }

        if (strstr($caja->variable_valor, '$')) {

            return preg_replace_callback(
                '/\$(\w+)/i',
                function($coincidencias) use ($personaje) {
                    return $personaje[$coincidencias[1]];
                },
                $caja->variable_valor
            );
        }
    }*/

    #
	public function salvar($cat)
	{
        $fichas_idu = (string)$cat['fichas_idu'];
		$idu = empty($cat['idu']) ? _str::uid() : $cat['idu'];
        unset($cat['fichas_idu'], $cat['idu']);

        $this->eliminar($idu, 'sin alerta');

        if ( ! empty($cat['fotos']) && is_array($cat['fotos']) && ! empty($_FILES['imagenes'])) {
            $files = $_FILES['imagenes'];
            foreach ($cat['fotos'] as $i => $foto) {
                if ( ! empty($files['name'][$i]) && $files['error'][$i] == 0) {
                    $payload = [
                        'imagenes' => [
                            'name' => [$files['name'][$i]],
                            'type' => [$files['type'][$i]],
                            'tmp_name' => [$files['tmp_name'][$i]],
                            'error' => [$files['error'][$i]],
                            'size' => [$files['size'][$i]],
                        ]
                    ];
                    $cat[$foto] = (new Archivos)->incluir($payload, 'fichas/retratos');
                }
            }
        }
        unset($cat['fotos']);

        foreach ($cat as $name=>$value)
        {
            # Generado aquí
            $keys[] = '(?, ?, ?, ?, ?)';
            $vals[] = Session::get('idu');
            $vals[] = (string)$fichas_idu;
            $vals[] = (string)$idu;
            $vals[] = (string)$name;
            $vals[] = (string)$value;
        }
        if ( ! $vals) {
            return;
        }
        $sql = 'INSERT INTO personajes (usuarios_idu, fichas_idu, idu, variable_nombre, variable_valor) VALUES ' . implode(', ', $keys);
        self::query($sql, $vals);

		Session::setArray('toast', t('Personaje salvado.'));

        return $idu;
    }

    #
	public function duplicar($cat)
	{
        $fichas_idu = (string)$cat['fichas_idu'];
		$idu = _str::uid();
        unset($cat['fichas_idu'], $cat['idu']);

        if ( ! empty($_FILES['imagenes']['name'][0])) {
            foreach ($cat['fotos'] as $foto) {
                $cat[$foto] = (new Archivos)->incluir($_FILES, 'fichas/retratos');
            }
        }
        unset($cat['fotos']);

        foreach ($cat as $name=>$value)
        {
            # Generado aquí
            $keys[] = '(?, ?, ?, ?, ?)';
            $vals[] = Session::get('idu');
            $vals[] = (string)$fichas_idu;
            $vals[] = (string)$idu;
            $vals[] = (string)$name;
            $vals[] = (string)$value;
        }
        if ( ! $vals) {
            return;
        }
        $sql = 'INSERT INTO personajes (usuarios_idu, fichas_idu, idu, variable_nombre, variable_valor) VALUES ' . implode(', ', $keys);
        self::query($sql, $vals);

		Session::setArray('toast', t('Personaje duplicado.'));

        return $idu;
    }

    #
    public function eliminar($idu, $alerta='si')
    {
        # El guardián puede borrar cualquier personaje.
        if (Session::get('rol') > 5) {
            $sql = 'DELETE FROM personajes WHERE idu=?';
            self::query($sql, [$idu]);
            if ($alerta=='si') {
                Session::setArray('toast', t('Personaje eliminado.'));
            }
            return;
        }

        # De lo contrario solo el propietario puede borrar un personaje.
        $sql = 'DELETE FROM personajes WHERE usuarios_idu=? AND idu=?';
        self::query($sql, [Session::get('idu'), $idu]);

        if ($alerta=='si') {
            Session::setArray('toast', t('Personaje eliminado.'));
        }
    }

    #
    public function todos($fichas_idu)
    {
        $sql = "SELECT * FROM personajes WHERE fichas_idu=?";
        $rows = self::all($sql, [$fichas_idu]);
        $personajes = [];
        foreach ($rows as $row) {
            $personajes[$row->idu][$row->variable_nombre] = $row->variable_valor;
        }  
        //$personajes[$row->idu]['usuarios_idu'] = $rows->usuarios_idu;
        return $personajes;
    }

    #
    public function uno($fichas_idu, $idu)
    {
		$sql = 'SELECT * FROM personajes WHERE idu=?';
        $cat = self::all($sql, [$idu]);
        if ($cat) {
            foreach ($cat as $obj) {
                $personaje[$obj->variable_nombre] = $obj->variable_valor;
            }  
            return $personaje;
        }
        $personaje = (new Fichas_cajas)->variables($fichas_idu);
        $personaje['idu'] = $idu;
        return $personaje;
    }
}
