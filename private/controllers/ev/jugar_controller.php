<?php
/**
 * Este es el panel de los jugadores.
 */
class JugarController extends EvController
{
	#
	protected function before_filter()
	{
		if ($action = Input::post('action')) {
			unset($_POST['action']);
			if (method_exists($this, $action)) {
				$this->$action();
			}
		}
		Input::isAjax() ? View::template('ajax') : View::template('jugador');
	}

	#
	public function partida($partidas_idu)
	{
		(new Partidas_usuarios)->conectar($partidas_idu);
		$this->conectados = (new Partidas_usuarios)->conectados($partidas_idu);
		$this->partida = (new Eventos)->uno($partidas_idu);
		$this->aventura = (new Aventuras)->una($this->partida->aventuras_idu);
		$this->ficha = (new Fichas)->una($this->aventura->fichas_idu);
		$this->personajes = (new Personajes)->todos($this->aventura->fichas_idu);
		$this->elementos = (new Partidas_elementos)->todos($partidas_idu, 'visibles');
		$this->elementos_tipos = Config::get('ev.elementos_tipos');
		$this->fondo = (new Partidas_elementos)->obtenerFondo($partidas_idu);
		$this->mensajes = (new Conversaciones_mensajes)->todos(['conversaciones_idu'=>$partidas_idu]);
		$this->add_to = '.chat textarea';
		View::select('elementos');
	}

	#
	public function quitar($elementos_idu)
	{
		$this->elemento = (new Aventuras_elementos)->uno($elementos_idu);
	}

	#
	public function recibir_elemento($elementos_idu)
	{
		$this->ele = (new Partidas_elementos)->uno($elementos_idu, 'visible');
		$this->elementos_idu = $elementos_idu;
		if ($this->ele) {
			return View::select('elemento');
		}
		View::select('quitar_elemento');
	}

	#
	public function recibir_elementos($partidas_idu)
	{
		$this->elementos = (new Partidas_elementos)->todos($partidas_idu, 'visibles');
		View::select('elementos');
	}

	#
	public function recibir_fondo($partidas_idu)
	{
		$this->fondo = (new Partidas_elementos)->obtenerFondo($partidas_idu);
	}

	#
	public function conectados($partidas_idu)
	{
		$this->conectados = (new Partidas_usuarios)->conectados($partidas_idu);
	}

	#
	public function actualizar()
	{
		$post = Input::post();

		if ($post['aplicar_en'] == 'partida' || $post['aplicar_en'] == 'ambas') {
			(new Partidas_elementos)->salvar($post);
		}

		if ($post['aplicar_en'] == 'aventura' || $post['aplicar_en'] == 'ambas') {
			(new Aventuras_elementos)->salvar($post);
		}

		View::select(null);

		_url::enviarAlCanal('elementos_' . $post['partidas_idu'], [
			'url' => '/ev/jugar/recibir_elementos/' . $post['partidas_idu'],
		]);
	}

	#
	public function crear()
	{
		$post = Input::post();

		if ($post['aplicar_en'] == 'aventura' || $post['aplicar_en'] == 'ambas') {
			(new Aventuras_elementos)->salvar($post);
		}

		if ($post['aplicar_en'] == 'partida' || $post['aplicar_en'] == 'ambas') {
			(new Partidas_elementos)->salvar($post);
		}

		View::select(null);

		_url::enviarAlCanal('elementos_' . $post['partidas_idu'], [
			'url' => '/ev/jugar/recibir_elementos/' . $post['partidas_idu'],
		]);
	}

	#
	public function eliminar()
	{
		$post = Input::post();
		$eid = !empty($post['elementos_idu']) ? $post['elementos_idu'] : $post['idu'];

		if ($post['aplicar_en'] == 'aventura' || $post['aplicar_en'] == 'ambas') {
			(new Aventuras_elementos)->eliminar($post);
		}

		if ($post['aplicar_en'] == 'partida' || $post['aplicar_en'] == 'ambas') {
			(new Partidas_elementos)->eliminar($eid);
		}

		View::select(null);

		_url::enviarAlCanal('elementos_' . $post['partidas_idu'], [
			'url' => '/ev/jugar/recibir_elementos/' . $post['partidas_idu'],
		]);
	}

	#
	public function enviar_elementos($partidas_idu, $elementos_idu)
	{
		(new Partidas_elementos)->mostrarElemento($elementos_idu);
		View::select(null, null);
	}
}
