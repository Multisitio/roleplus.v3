<?php
#
class Grupos extends LiteRecord
{
    # R+2
    public function actualizarContador($grupos_idu)
    {
        $miembros = (new Usuarios_grupos)->contar($grupos_idu);
        $sql = 'UPDATE grupos SET miembros=? WHERE idu=?';
        self::query($sql, [$miembros, $grupos_idu]);
    }

    #
    public function eliminar($grupos_idu)
    {
        $sql = 'DELETE FROM grupos WHERE usuarios_idu=? AND idu=?';
        if ( self::query($sql, [Session::get('idu'), $grupos_idu]) )
            Session::setArray('toast', t('!Eliminaste el grupo!'));

        (new Actividades)->eliminar(['elemento_idu'=>$grupos_idu]);
    }

    # R+2
    public function salvar($post)
    {
        #_var::die($post);
        $values[] = Session::get('idu');
        $values[] = $nombre = str_replace('/', '|', trim($post['nombre']));

        if ($post['nombre_anterior'] <> $nombre) {
            if (strlen($nombre) < 3) {
                return Session::setArray('toast', t('El grupo ha de tener un mínimo de 3 caracteres.'));
            }
            $sql = 'SELECT * FROM grupos WHERE usuarios_idu=? AND nombre=?';
            $grupo = self::first($sql, $values);
            if ($grupo) {
                Session::setArray('toast', 'Lo siento, ess nombre está pillado.');return _str::hashtag($post['nombre_anterior']);
            }
        }

		$values[] = $grupos_idu = empty($post['idu']) ? _str::uid() : $post['idu'];
		$values[] = $hashtag = _str::hashtag($nombre);
		$values[] = (string)$post['eslogan'];
		$values[] = (string)$post['info'];

        $dir = 'img/usuarios/' . Session::get('idu');
        $values[] = empty($_FILES['fondo_cabecera']['name']) ? $post['fondo_cabecera'] : self::saveOneFile('fondo_cabecera', $dir);
        $values[] = empty($_FILES['fondo_general']['name']) ? $post['fondo_general'] : self::saveOneFile('fondo_general', $dir);
        $values[] = empty($_FILES['mapa']['name']) ? $post['mapa'] : self::saveOneFile('mapa', $dir);

        if (empty($post['idu'])) {
            $sql = "INSERT INTO grupos SET usuarios_idu=?, nombre=?, idu=?, hashtag=?, eslogan=?, info=?, fondo_cabecera=?, fondo_general=?, mapa=?";
            (new Usuarios_grupos)->unirse($grupos_idu);
		    Session::setArray('toast', t('Grupo creado.'));
        }
        else {
            $values[] = (string)$post['idu'];
            $sql = "UPDATE grupos SET usuarios_idu=?, nombre=?, idu=?, hashtag=?, eslogan=?, info=?, fondo_cabecera=?, fondo_general=?, mapa=? WHERE idu=?";
		    Session::setArray('toast', t('Grupo actualizado.'));
        }
        self::query($sql, $values);

        # Registrando actividad
        /*(new Actividades)->crear([
            'tipo'=>'grupos',
            'elemento_nombre'=>$nombre,
            'elemento_idu'=>$grupos_idu,
        ]);*/

		return $hashtag;
	}

    # 
	static public function saveOneFile($key, $dir, $new_name='')
	{
		if (empty($_FILES[$key])) {
			return ['error' => 'no key'];
		}
		$file = $_FILES[$key];
		
		if ( ! empty($file['error'])) {
			return ['error' => $file['error']];
		}

		$idu = _str::uid();

		$ext = explode('/', $file['type'], 2)[1];

		$name = empty($new_name) ? $key . "_$idu.$ext" : $new_name;

		$bool = move_uploaded_file($file['tmp_name'], "$dir/$name");

		#_var::die([$_FILES[$key], "$dir/$name", $r]);
		
		if ( ! $bool) {
			return ['error' => $bool];
		}

		return $name;
	}

    #
    public function todos($a=[])
    {
        $sql = 'SELECT * FROM grupos';
        if ( ! empty($a['order']) ) $sql .= ' ORDER BY ' . $a['order'];
        $grupos = self::all($sql);
        foreach ($grupos as $o) $r[$o->idu] = $o;
        return $r;
    }

    #
    public function todosLosHashtags()
    {
        $sql = 'SELECT hashtag FROM grupos ORDER BY hashtag';
        $rows = parent::all($sql);
        foreach ($rows as $row) {
            $grupos[$row->hashtag] = $row->hashtag;
        }
        return $grupos;
    }

    # R+2
    public function todosMisGrupos()
    {
        $sql = 'SELECT * FROM grupos WHERE usuarios_idu=?';
        return self::all($sql, [Session::get('idu')]);
    }

    # R+2
    public function ultimaPub($idu)
    {
        $sql = "UPDATE grupos SET ultima_pub=? WHERE idu=?";
        self::query($sql, [date('Y-m-d H:i:s'), $idu]);
    }

    # R+2
    public function uno($grupos_idu)
    {
		$sql = 'SELECT * FROM grupos WHERE idu=?';
        $uno = self::first($sql, [$grupos_idu]);
        return empty($uno) ? self::cols() : $uno;
    }

    # R+2
    public function unoPorNombre($grupo_nombre)
    {
		$grupo_nombre = urldecode($grupo_nombre);
		$sql = 'SELECT * FROM grupos WHERE nombre=?';
        return self::first($sql, [$grupo_nombre]);
    }

    # R+2
    public function unoPorHashtag($hashtag)
    {
		$sql = 'SELECT * FROM grupos WHERE hashtag=?';
        return self::first($sql, [$hashtag]);
    }

    # https://roleplus.app/grupos/ver/Pbta+en+castellano
    /*public function vistaPrevia($grupo_nombre)
    {
        $gru = self::unoPorNombre($grupo_nombre);
        if (empty($gru->usuarios_idu)) return;
        $usu = (new Usuarios)->uno($gru->usuarios_idu);
        $a['title'] = $gru->nombre;
        $descripcion = empty($gru->info) ? $gru->eslogan : "$gru->eslogan\n$gru->info";
        $a['description'] = $descripcion;
        $a['thumbnail'] = "https://roleplus.app/img/usuarios/$usu->idu/$gru->fondo_cabecera";
        $a['url'] = "https://roleplus.app/grupos/ver/$grupo_nombre";
        $a['image'] = "https://roleplus.app/img/usuarios/$usu->idu/l.$gru->fondo_cabecera";
        return $a;
    }*/
}
