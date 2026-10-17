<?php
class Personajes extends LiteRecord
{
    public function estado($ficha, $idu)
    {
        $row = self::first('SELECT * FROM personajes_estado WHERE idu=? AND fichas_idu=? AND eliminado=0', [$idu,$ficha]);
        if ( ! $row) { throw new RuntimeException('Personaje inexistente.', 404); }
        $datos = [];
        foreach (self::all('SELECT variable_nombre,variable_valor FROM personajes WHERE idu=? ORDER BY id', [$idu]) as $campo) {
            $datos[$campo->variable_nombre] = $campo->variable_valor ?? '';
        }
        return ['idu'=>$idu, 'revision'=>(int)$row->revision, 'datos'=>$datos, 'editable'=>(bool)$this->esPropietario($idu)];
    }

    public function esPropietario($idu)
    {
        $row = self::first('SELECT usuarios_idu FROM personajes_estado WHERE idu=? AND eliminado=0', [$idu]);
        return Session::get('idu') && $row && ($row->usuarios_idu == Session::get('idu') || Session::get('rol') > 5);
    }

    public function salvar($cat)
    {
        if ( ! Session::get('idu')) { throw new RuntimeException('Sesión ausente.', 401); }
        $ficha = (string)$cat['fichas_idu'];
        $idu = (string)($cat['idu'] ?? '');
        if ( ! preg_match('/^[a-f0-9]{12}$/D', $idu)) { throw new RuntimeException('Identificador de personaje inválido.', 400); }
        $campos = isset($cat['campos']) ? json_decode($cat['campos'], true, 512, JSON_THROW_ON_ERROR) : null;
        $fotos = $cat['fotos'] ?? [];
        unset($cat['fichas_idu'], $cat['idu'], $cat['action'], $cat['campos'], $cat['estado_inicial'], $cat['fotos'], $cat['confirmar_eliminacion']);
        if ($campos === null) { $campos = array_keys($cat); }
        if ( ! is_array($campos) || ! $campos) { throw new RuntimeException('No hay campos para guardar.', 400); }
        self::query('START TRANSACTION');
        try {
            self::query('INSERT IGNORE INTO personajes_estado (idu,fichas_idu,usuarios_idu) VALUES (?,?,?)', [$idu,$ficha,Session::get('idu')]);
            $estado = self::first('SELECT * FROM personajes_estado WHERE idu=? FOR UPDATE', [$idu]);
            if ($estado->eliminado) { throw new RuntimeException('El personaje ha sido eliminado; se conserva para recuperación.', 410); }
            if ($estado->fichas_idu !== $ficha || ! $this->esPropietario($idu)) {
                throw new RuntimeException('No puedes editar este personaje.', 403);
            }
            foreach ($fotos as $i=>$foto) {
                if ( ! in_array($foto, $campos, true) || empty($_FILES['imagenes']['name'][$i])) { continue; }
                $files = $_FILES['imagenes'];
                if ($files['error'][$i] !== UPLOAD_ERR_OK) { throw new RuntimeException('Error al subir el retrato.', 400); }
                $payload = ['imagenes'=>[]];
                foreach (['name','type','tmp_name','error','size'] as $key) { $payload['imagenes'][$key] = [$files[$key][$i]]; }
                $cat[$foto] = (new Archivos)->incluir($payload, 'fichas/retratos');
            }
            foreach (array_unique($campos) as $nombre) {
                if ( ! is_string($nombre) || $nombre === '' || strlen($nombre)>111 || is_array($cat[$nombre] ?? null)) {
                    throw new RuntimeException('Campo inválido.', 400);
                }
                $valor = (string)($cat[$nombre] ?? '');
                self::query('INSERT INTO personajes (usuarios_idu,fichas_idu,idu,variable_nombre,variable_valor) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE variable_valor=VALUES(variable_valor)', [$estado->usuarios_idu,$ficha,$idu,$nombre,$valor]);
            }
            self::query('UPDATE personajes_estado SET revision=revision+1 WHERE idu=?', [$idu]);
            self::query('COMMIT');
        } catch (Throwable $e) { self::query('ROLLBACK'); throw $e; }
        if ( ! Input::isAjax()) { Session::setArray('toast', t('Personaje salvado.')); }
        return $idu;
    }

    public function duplicar($cat)
    {
        $cat['idu'] = _str::uid();
        unset($cat['campos'], $cat['estado_inicial']);
        return $this->salvar($cat);
    }

    public function eliminar($idu, $alerta='si')
    {
        self::query('START TRANSACTION');
        try {
            self::first('SELECT idu FROM personajes_estado WHERE idu=? FOR UPDATE', [$idu]);
            if ( ! $this->esPropietario($idu)) { throw new RuntimeException('No puedes eliminar este personaje.', 403); }
            // Keep the complete character and its link recoverable. A stale tab
            // must see this tombstone instead of recreating a partial character.
            self::query('UPDATE personajes_estado SET eliminado=1,revision=revision+1 WHERE idu=?', [$idu]);
            (new Historico)->add('personaje:' . $idu, 'activo', 'eliminado');
            self::query('COMMIT');
        } catch (Throwable $e) { self::query('ROLLBACK'); throw $e; }
        if ($alerta === 'si') { Session::setArray('toast', t('Personaje eliminado.')); }
    }

    public function todos($ficha)
    {
        $personajes = [];
        foreach (self::all('SELECT p.* FROM personajes p JOIN personajes_estado e ON e.idu=p.idu WHERE p.fichas_idu=? AND e.eliminado=0 ORDER BY p.id', [$ficha]) as $row) {
            $personajes[$row->idu][$row->variable_nombre] = $row->variable_valor;
        }
        return $personajes;
    }

    public function uno($ficha, $idu)
    {
        $personaje = (new Fichas_cajas)->variables($ficha) ?: [];
        foreach (self::all('SELECT variable_nombre,variable_valor FROM personajes WHERE idu=? AND fichas_idu=? ORDER BY id', [$idu,$ficha]) as $row) {
            $personaje[$row->variable_nombre] = $row->variable_valor;
        }
        return $personaje;
    }
}
