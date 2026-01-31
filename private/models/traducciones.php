<?php
/**
 */
class Traducciones extends LiteRecord
{
	#
	public function arreglo()
	{
		$sql = 'SELECT * FROM traducciones_propuestas';
		$propuestas = self::all($sql);
		foreach ($propuestas as $pro) {
			$sql = 'SELECT * FROM traducciones WHERE idioma=? AND traduccion=?';
			$tra = self::first($sql, [$pro->idioma, $pro->traduccion]);

			if ( ! $tra) {
				$sql = 'DELETE FROM traducciones_propuestas WHERE id=?';
				$delete = self::query($sql, [$pro->id]);
				_var::flush([$pro, $delete]);
				continue;
			}

			$sql = 'UPDATE traducciones_propuestas SET traducir=? WHERE id=?';
			$update = self::query($sql, [$tra->traducir, $pro->id]);

			_var::flush([$pro, $tra, $update]);
		}
	}

	#
	public function buscar($post)
	{
		if (strlen($post['busqueda']) < 3) {
			Session::setArray('toast', t('Busque mínimo 3 caracteres.'));
			return [];
		}

		$sql = 'SELECT * FROM traducciones WHERE revisando=0';
		
		if ($post['idioma']) {
			$sql .= ' AND idioma=?';
			$values[] = $post['idioma'];
		}
		
		$sql .= ' AND (traducir LIKE ? OR traduccion LIKE ?) LIMIT 10';

		$values[] = "%{$post['busqueda']}%";
		$values[] = "%{$post['busqueda']}%";
		
		$traducciones = self::all($sql, $values);
		#_var::die([$sql, $post, $traducciones]);
		return $traducciones;
	}

	#
	public function enviarPropuesta($post)
	{
		$id = $post['id'];

		$sql = 'SELECT * FROM traducciones WHERE id=?';
		$tra = self::first($sql, [$id]);

		$sql = 'UPDATE traducciones SET revisando=1 WHERE id=?';
		self::query($sql, [$id]);

		$values[] = Session::get('idu');
		$values[] = $tra->idioma;
		$values[] = $tra->traducir;
		$values[] = $tra->traduccion;
		$values[] = $post['propuesta'][$id];
		$values[] = $post['comentario_traductor'][$id];

		$sql = 'INSERT INTO traducciones_propuestas SET usuarios_idu=?, idioma=?, traducir=?, traduccion=?, propuesta=?, comentario_traductor=?';
		self::all($sql, $values);

        Session::setArray('toast', t('GRACIAS, propuesta enviada.'));

		_mail::send('dj@roleplus.app', 'Propuesta de traducción', '<pre>'.print_r($post, 1) . "\n\nhttps://roleplus.app/atalaya/traducciones");
	}

	#
	public function propuestas($estado='')
	{
		$sql = 'SELECT t_p.*, usu.apodo, usu.hashtag, usu.avatar, usu.email, usu.rol FROM traducciones_propuestas t_p, usuarios usu WHERE t_p.usuarios_idu=usu.idu';
		
		if ($estado == 'rechazadas') {
			$sql .= ' AND rechazada IS NOT NULL';
		}
		elseif ($estado == 'aprobadas') {
			$sql .= ' AND aprobada IS NOT NULL';
		}
		elseif ($estado == 'pendientes') {
			$sql .= ' AND aprobada IS NULL AND rechazada IS NULL';
		}
		return self::all($sql);
	}

	#
	public function aceptarPropuesta($post)
	{
		$id = $post['id'];

		$sql = 'SELECT * FROM traducciones_propuestas WHERE id=?';
		$pro = self::first($sql, [$id]);

		$values[] = $pro->propuesta;
		$values[] = $pro->idioma;
		$values[] = $pro->traducir;

		$sql = 'UPDATE traducciones SET traduccion=? WHERE idioma=? AND traducir=?';
		$update= self::query($sql, $values);
		#_var::die([$sql, $values, $update]);
		unset($values);

		$px = $post['experiencia'][$id];
		$values[] = $post['comentario_supervisor'][$id];
		$values[] = $px;
		$values[] = _date::format();
		$values[] = $id;

		$sql = 'UPDATE traducciones_propuestas SET comentario_supervisor=?, experiencia=?, aprobada=? WHERE id=?';
		self::query($sql, $values);

		#_var::die([$px, Session::get('idu'), $post['usuarios_idu'], "$px PX por la traducción $id."]);

		(new Experiencia)->registrar($px, Session::get('idu'), $post['usuarios_idu'], "$px PX por la traducción $id.");
		
        $this->volcarAFichero();
	}

	# TEST OK
	public function rechazarPropuesta($post)
	{
		$id = $post['id'];
		$values[] = $post['comentario_supervisor'][$id];
		$values[] = _date::format();
		$values[] = $id;

		$sql = 'UPDATE traducciones_propuestas SET comentario_supervisor=?, rechazada=? WHERE id=?';
		$update = self::query($sql, $values);
		#_var::die([$sql, $values, $update]);
	}

	#
	public function volcarABaseDeDatos()
	{
		$fichero = Config::get('combos.idiomas');

		foreach ($fichero as $idioma=>$traducciones) {
			foreach ($traducciones as $traducir=>$traduccion) {
				$keys[] = '(?, ?, ?)';
				$vals[] = $idioma;
				$vals[] = $traducir;
				$vals[] = $traduccion;
			}
		}
        $sql = 'INSERT INTO traducciones (idioma, traducir, traduccion) VALUES ' . implode(', ', $keys);
        #$r = self::query($sql, $vals);
		_var::die($r);
	}

	#
	public function volcarAFichero()
	{
		$sql = 'SELECT * FROM traducciones';
		$traducciones = self::all($sql);

		foreach ($traducciones as $tra) {
			$preaprado[mb_strtoupper($tra->idioma)][$tra->traducir] = $tra->traduccion;
		}

		unset($traducciones);
		foreach ($preaprado as $idioma=>$traducciones) {
			$put = file_put_contents(APP_PATH . "config/idiomas_$idioma.php", "<?php\n\nreturn " . var_export($traducciones, 1) . ";\n");

			($put) 
				? Session::setArray('toast', "[$idioma] " . t('Archivo generado.'))
				: Session::setArray('toast', "[$idioma] " . t('Error generando archivo.'));
		}
	}
}
