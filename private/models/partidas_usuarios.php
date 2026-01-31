<?php
/**
 */
class Partidas_usuarios extends LiteRecord
{
	#
	public function conectar($partidas_idu)
	{
		$una = $this->una($partidas_idu);
		if ($una) {
			$sql = 'UPDATE partidas_usuarios SET ultima_vez=? WHERE id=?';
			return self::query($sql, [date('Y-m-d H:i:s'), $una->id]);
		}
		$sql = 'INSERT INTO partidas_usuarios SET usuarios_idu=?, partidas_idu=?, ultima_vez=?';
		self::query($sql, [Session::get('idu'), $partidas_idu, date('Y-m-d H:i:s')]);
	}

	#
	public function conectado($partidas_idu)
	{
		$sql = 'DELETE FROM partidas_usuarios WHERE usuarios_idu=? AND partidas_idu=?';
		self::query($sql, [Session::get('idu'), $partidas_idu]);

		$sql = 'INSERT INTO partidas_usuarios SET ultima_vez=?, usuarios_idu=?, partidas_idu=?';
		self::query($sql, [date('Y-m-d H:i:s'), Session::get('idu'), $partidas_idu]);

        _url::enviarAlCanal('ev_conectados', [
            'url' => '/ev/panel/conectados/' . $partidas_idu,
        ]);
	}

	#
	public function conectados($partidas_idu)
	{
		$ultima_vez = date('Y-m-d H:i:s', strtotime('-10 minutes'));

		$sql = 'SELECT p_u.*, usu.apodo, usu.hashtag FROM partidas_usuarios p_u
			LEFT JOIN usuarios usu ON p_u.usuarios_idu=usu.idu
			WHERE p_u.partidas_idu=? AND p_u.ultima_vez>?';

		$cat = self::all($sql, [$partidas_idu, $ultima_vez]);

		$conectados = [];
		foreach ($cat as $obj) {
			$conectados[$obj->usuarios_idu] = $obj;
		}
		return $conectados;
	}

	#
	public function enviar($partidas_idu, $url, $contenedor, $adjuntar=0)
	{
		$adjuntar = empty($adjuntar) ? 0 : 1;

        $cat = (new Partidas_usuarios)->conectados($partidas_idu);
        foreach ($cat as $obj) {
            $conectados[$obj->usuarios_idu] = $obj;
        }
		unset($conectados[Session::get('idu')]);
        (new Actualizaciones)->cargarUsuarios([
            'usuarios'=>$conectados,
            'contenido'=>$url,
            'adjuntar'=>$adjuntar,
            'contenedor'=>$contenedor,
            'fecha'=>date('Y-m-d H:i:s'),
            #'debug'=>1,
        ],
        $conectados);
		#_var::die([$partidas_idu, $conectados, $url, $adjuntar, $contenedor]);
	}

	#
	public function una($partidas_idu)
	{
		$sql = 'SELECT * FROM partidas_usuarios WHERE usuarios_idu=? AND partidas_idu=?';
		return self::first($sql, [Session::get('idu'), $partidas_idu]);
	}
}
