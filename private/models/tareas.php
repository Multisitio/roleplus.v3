<?php
/**
 */
class Tareas extends LiteRecord
{
	#
	public function cambiarEstado($post)
	{
		$usuario = (new Usuarios)->uno();

		$id = $post['id'];

		$sql = 'SELECT * FROM tareas WHERE id=?';
		$tra = self::first($sql, [$id]);

		$values[] = empty($post['comentarios'][$id])
			? $tra->comentarios
			: $tra->comentarios . "\n---\n(" . _date::format() . ") $usuario->apodo:\n" . $post['comentarios'][$id];
		$values[] = _date::format();
		$values[] = $id;

		$sql = 'UPDATE tareas SET comentarios=?';
		if ($post['estado'] == 'aprobada') {
			$sql .= ', aprobada=?';
		}
		elseif ($post['estado'] == 'rechazada') {
			$sql .= ', rechazada=?';
		}
		elseif ($post['estado'] == 'pospuesta') {
			$sql .= ', pospuesta=?';
		}
		elseif ($post['estado'] == 'terminada') {
			$sql .= ', terminada=?';
		}
		$sql .= ' WHERE id=?';
		self::query($sql, $values);
	}

	#
	public function comentarPropuesta($post)
	{
		$usuario = (new Usuarios)->uno();

		$id = $post['id'];

		$sql = 'SELECT * FROM tareas WHERE id=?';
		$tra = self::first($sql, [$id]);

		$values[] = ($tra->comentarios)
			? $tra->comentarios . "\n---\n(" . _date::format() . ") $usuario->apodo:\n" . $post['comentarios'][$id]
			: "$usuario->apodo:\n" . $post['comentarios'][$id];

		$notificar = unserialize($tra->notificar);
		if (empty($notificar[Session::get('idu')])) {
			$notificar[Session::get('idu')] = Session::get('idu');
			$values[] = serialize($notificar);
		}
		else {
			$values[] = $tra->notificar;
		}

		$values[] = $id;

		$sql = 'UPDATE tareas SET comentarios=?, notificar=? WHERE id=?';
		self::all($sql, $values);

        Session::setArray('toast', t('Comentario enviado.'));

		foreach ($notificar as $usuarios_idu) {

			if (Session::get('idu') == $usuarios_idu) {
				continue;
			}

			(new Notificaciones)->notificarUna([
				'para_idu' => $usuarios_idu,
				'que' => 'comentando tarea',
				'mensaje' => "Sobre una tarea...\n" . $post['comentarios'][$id],
				'donde' => 'tareas',
				'donde_idu' => $tra->idu
			]);
		}

		_mail::send('dj@roleplus.app', 'Propuesta de tarea', '<pre>'.print_r($post, 1) . "\n\nhttps://roleplus.app/atalaya/tareas");
	}

	#
	public function enviarPropuesta($post)
	{
		$usuario = (new Usuarios)->uno($post['usuarios_idu']);

		$id = $post['id'];

		$sql = 'SELECT * FROM tareas WHERE id=?';
		$tra = self::first($sql, [$id]);

		$values[] = _str::uid();
		$values[] = Session::get('idu');
		$values[] = $post['propuesta'];
		$values[] = empty($post['comentarios'])
			? ''
			: '(' . _date::format() . ") $usuario->apodo:\n" . $post['comentarios'];
		$values[] = _date::format();

		$sql = 'INSERT INTO tareas SET idu=?, usuarios_idu=?, propuesta=?, comentarios=?, creada=?';
		self::all($sql, $values);

        Session::setArray('toast', t('GRACIAS, propuesta enviada.'));

		_mail::send('dj@roleplus.app', 'Propuesta de tarea', '<pre>'.print_r($post, 1) . "\n\nhttps://roleplus.app/atalaya/tareas");
	}

	#
	public function propuestas($estado='', $tareas_idu='')
	{
		$sql = 'SELECT tar.*, usu.apodo, usu.hashtag, usu.avatar, usu.email, usu.rol FROM tareas tar, usuarios usu WHERE tar.usuarios_idu=usu.idu';
		
		if ($estado == 'una') {
			$sql .= ' AND tar.idu=?';
			return self::all($sql, [$tareas_idu]);
		}
		elseif ($estado == 'aprobadas') {
			$sql .= ' AND tar.aprobada IS NOT NULL';
		}
		elseif ($estado == 'rechazadas') {
			$sql .= ' AND tar.rechazada IS NOT NULL';
		}
		elseif ($estado == 'pospuestas') {
			$sql .= ' AND tar.pospuesta IS NOT NULL';
		}
		elseif ($estado == 'terminadas') {
			$sql .= ' AND tar.terminada IS NOT NULL';
		}
		return self::all($sql);
	}
}
