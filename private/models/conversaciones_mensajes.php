<?php
/**
 */
class Conversaciones_mensajes extends LiteRecord
{
	#
	public function validar($men)
	{
		$men['conversaciones_idu'] = empty($men['conversaciones_idu'])
			? null
			: (string)$men['conversaciones_idu'];

		$men['usuarios_idu'] = ( ! empty($men['usuarios_idu']) && $men['usuarios_idu'] == '9bb57d4c075')
			? '9bb57d4c075'
			: Session::get('idu');

		$men['apartado'] = ( ! empty($men['apartado'])
			and preg_match('/personaje|notas/', $men['apartado']))
				? $men['apartado']
				: 'jugador';

		$men['contenido'] = trim($men['contenido']);
		$men['contenido'] = _html::stripTags($men['contenido']);
		$men['contenido'] = _dices::throw($men['contenido']);
		
		$men['idu'] = empty($men['idu'])
			? _str::uid()
			: (string)$men['idu'];

		$men['para_usuarios_idu'] = empty($men['para_usuarios_idu'])
		? null
		: $men['para_usuarios_idu'];

		$men['creado'] = date('Y-m-d H:i:s');
		$men['leido'] = date('Y-m-d H:i:s');
		$men['editado'] = date('Y-m-d H:i:s');
		$men['borrado'] = date('Y-m-d H:i:s');
		return $men;
	}

	#
	/*public function borrar($mensajes_idu)
	{
		$sql = 'DELETE FROM conversaciones_mensajes WHERE usuarios_idu=? AND idu=?';
		self::query($sql, [Session::get('idu'), $mensajes_idu]);
	}*/

	#
	public function crear($arr)
	{
		$arr = $this->validar($arr);

		(new Conversaciones)->registrarUltimo($arr);

		$sql = 'INSERT INTO conversaciones_mensajes SET conversaciones_idu=?, usuarios_idu=?, apartado=?, contenido=?, idu=?, para_usuarios_idu=?, creado=?';
		self::query($sql, [$arr['conversaciones_idu'], $arr['usuarios_idu'], $arr['apartado'], $arr['contenido'], $arr['idu'], $arr['para_usuarios_idu'], $arr['creado']]);

		$usuarios = (new Conversaciones_usuarios)->todos(['conversaciones_idu'=>$arr['conversaciones_idu']]);

		(new Notificaciones)->conversando($usuarios, $arr['contenido'], $arr['conversaciones_idu'], $arr['apartado']);

		/*if (empty($arr['para_usuarios_idu']) && ! empty($usuarios['9bb57d4c075'])) {
			unset($usuarios['9bb57d4c075']);
			$para = array_keys($usuarios)[0];

			$mensajes = self::todos(['conversaciones_idu'=>$arr['conversaciones_idu']]);
			$contexto = [];
			foreach ($mensajes as $men) {
				$contexto[] = "($men->apodo) $men->contenido";
			}
			$contexto = implode("\n\n", $contexto);
			#$contexto = '';

			$respuesta = (new Respuestas)->preguntarAOpenAi("Eres la i-A de R+ (https://ROLEplus.app) la red social de juegos de mesa y rol, a veces los usuarios preguntan cosas y otras veces quieren jugar a rol contigo, tú les diriges haciendo de Director de Juego (nunca sugieras soluciones, di directamente lo que ven los jugadores y lo que obtienen lanzando tú 2d6: con un resultado de 10 o más: le dices que lo ha logrado; con un resultado de 7 a 9: lo logra pero con una consecuencia, dile la consecuencia; con un resultado de 6 o menos: no lo logra y sufre una consecuencia, dile cual), te adjunto los mensajes anteriores a la pregunta para que te sirva de contexto: $contexto\n\nTienes que responder a esta pregunta de forma breve y precisa: {$arr['contenido']}");

			self::crear([
				'conversaciones_idu' => $arr['conversaciones_idu'],
				'usuarios_idu' => '9bb57d4c075',
				'contenido' => $respuesta,
				'para_usuarios_idu' => $para,
			]);
		}
		else {*/
			_url::enviarAlCanal('ev_chat_' . $arr['conversaciones_idu'],
				"/registrados/conversaciones/recibir/{$arr['idu']}");

			_url::enviarAlCanal('ev_chat_sin_leer_' . $arr['conversaciones_idu'], 
				"/ev/partidas/chat_sin_leer/{$arr['conversaciones_idu']}");

			_url::enviarAlCanal('rp_chat_' . $arr['conversaciones_idu'], "/registrados/conversaciones/recibir/{$arr['idu']}");

			(new Conversaciones)->propagarListados($arr['conversaciones_idu']);
		/*}*/
	}

	#
	/*public function editar($men)
	{
		$men = $this->validar($men);
		$sql = 'UPDATE conversaciones_mensajes SET apartado=?, contenido=?, editado=? WHERE usuarios_idu=? AND idu=?';
		self::query($sql, [$men['apartado'], $men['contenido'], $men['editado'], $men['usuarios_idu'], $men['idu']]);
		return $this->uno($men['idu']);

	}*/

	#
	public function sinLeer($conversaciones_idu)
	{
		$ultimo_leido = (new Conversaciones_usuarios)->obtenerUltimoLeido($conversaciones_idu);

		$sql = 'SELECT id FROM conversaciones_mensajes WHERE conversaciones_idu=? AND NOT usuarios_idu=?';
		$values[] = $conversaciones_idu;
		$values[] = Session::get('idu');

		if ($ultimo_leido) {
			$sql .= ' AND creado>?';
			$values[] = $ultimo_leido;
		}

		$sin_leer = self::all($sql, $values);

		if ( ! $sin_leer) {
			return 0;
		}
		return count($sin_leer);
	}

	#
	public function todos($men, $apartado='jugador')
	{
		$values[] = $men['conversaciones_idu'];

		$values[] = preg_match('/personaje|notas/', $apartado)
			? $apartado
			: 'jugador';

		$ultimos = '';
		if ( ! empty($men['ultimo_fecha'])) {

			$operador = ( ! empty($men['accion']) && $men['accion'] == 'recibir')
				? '>='
				: '>';

			$ultimos = " AND c_m.creado$operador?";
			$values[] = $men['ultimo_fecha'];
		}

		$sql = "SELECT c_m.*, usu.apodo, usu.hashtag, usu.avatar, usu.email, usu.rol FROM conversaciones_mensajes c_m, usuarios usu WHERE c_m.usuarios_idu=usu.idu AND c_m.conversaciones_idu=? AND c_m.apartado=?$ultimos ORDER BY c_m.creado DESC LIMIT 10";
		#_mail::send('distrotuz@gmail.com', 'Conversaciones', $sql);
		#return [$sql, $values];
		$mensajes = self::all($sql, $values);
		return array_reverse($mensajes);
	}

	#
	public function uno($idu)
	{
		$sql = 'SELECT c_m.*, usu.apodo, usu.hashtag, usu.avatar, usu.email, usu.rol FROM conversaciones_mensajes c_m, usuarios usu WHERE c_m.usuarios_idu=usu.idu AND c_m.idu=?';

		return self::first($sql, [$idu]);
	}

	#
	/*public function ultimos($conversaciones_idu, $apartado='jugador')
	{
		$apartado = preg_match('/personaje|notas/', $apartado)
			? $apartado
			: 'jugador';

		$ultimo_leido = (new Conversaciones_usuarios)->obtenerUltimoLeido($conversaciones_idu);

		if ($ultimo_leido) {
			$sql = 'SELECT * FROM conversaciones_mensajes WHERE conversaciones_idu=? AND apartado=? AND creado>=? ORDER BY creado';
			return self::all($sql, [$conversaciones_idu, $apartado, $ultimo_leido]);
		}
		$sql = 'SELECT * FROM conversaciones_mensajes WHERE conversaciones_idu=? AND apartado=? ORDER BY creado';
		return self::all($sql, [$conversaciones_idu, $apartado]);
	}

	#
	public function uno($mensajes_idu)
	{
		$sql = 'SELECT * FROM conversaciones_mensajes WHERE usuarios_idu=? AND idu=?';
		return self::first($sql, [Session::get('idu'), $mensajes_idu]);
	}*/

	#
	public function vaciar($conversaciones_idu)
	{
		$sql = 'DELETE FROM conversaciones_mensajes WHERE conversaciones_idu=?';
		self::query($sql, [$conversaciones_idu]);
	}
}
