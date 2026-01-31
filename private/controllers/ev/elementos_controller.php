<?php
/**
 * Controlador unificado de elementos (aventura y partida).
 */
class ElementosController extends EvController
{
	protected function before_filter()
	{
		if ($action = Input::post('action')) {
			unset($_POST['action']);
			if (method_exists($this, $action)) {
				$this->$action();
			}
		}
		// EvController ya fija el template según AJAX.
	}

	public function editar_aventura($aventuras_idu, $elementos_idu = '')
	{
		if ($elementos_idu) {
			$this->elemento = (new Aventuras_elementos)->uno($elementos_idu);
			$aventuras_idu = $this->elemento->aventuras_idu ?: $aventuras_idu;
		}

		$this->partida = (object)[
			'idu' => '',
			'aventuras_idu' => $aventuras_idu
		];

		$this->elementos_tipos = Config::get('ev.elementos_tipos');
		$this->panel = 'aventura';
		View::select('ventana');
	}

	public function editar_partida($partidas_idu, $elementos_idu = '')
	{
		if ($elementos_idu) {
			$this->elemento = (new Partidas_elementos)->uno($elementos_idu);
		}
		$this->partida = (new Eventos)->uno($partidas_idu);
		$this->elementos_tipos = Config::get('ev.elementos_tipos');
		$this->panel = 'partida';
		View::select('ventana');
	}

	public function importar($aventuras_idu, $partida_idu)
	{
		(new Partidas_elementos)->importarElementos($aventuras_idu, $partida_idu);
		Redirect::to('/ev/panel/partida/' . $partida_idu);
	}

	public function crear()
	{
		$post = Input::post();

		// AMBAS sin UID: generar común
		if (($post['aplicar_en'] === 'ambas') && empty($post['elementos_idu']) && empty($post['idu'])) {
			$uid = _str::uid();
			$post['elementos_idu'] = $uid;
			$post['idu'] = $uid;
		}

		$eid_insert_av = '';
		$eid_insert_pa = '';

		if ($post['aplicar_en'] === 'aventura' || $post['aplicar_en'] === 'ambas') {
			if (empty($post['idu']) && !empty($post['elementos_idu'])) $post['idu'] = $post['elementos_idu'];
			// INSERT devuelve idu; UPDATE devuelve bool → NO fiarse en UPDATE
			$eid_insert_av = (string)(new Aventuras_elementos)->salvar($post);
		}

		if ($post['aplicar_en'] === 'partida' || $post['aplicar_en'] === 'ambas') {
			if (empty($post['elementos_idu']) && !empty($post['idu'])) $post['elementos_idu'] = $post['idu'];
			// INSERT devuelve elementos_idu; UPDATE devuelve bool → NO fiarse en UPDATE
			$eid_insert_pa = (string)(new Partidas_elementos)->salvar($post);
		}

		// UID efectivo para SSE:
		// 1) si ya venía en POST (crea/update) úsalo
		// 2) si era INSERT y el modelo devolvió UID, úsalo
		$eid = '';
		if (!empty($post['elementos_idu'])) {
			$eid = (string)$post['elementos_idu'];
		} elseif ($eid_insert_pa !== '') {
			$eid = $eid_insert_pa;
		} elseif (!empty($post['idu'])) {
			$eid = (string)$post['idu'];
		} elseif ($eid_insert_av !== '') {
			$eid = $eid_insert_av;
		}

		if ($eid !== '') {
			$post['elementos_idu'] = $eid;
			$post['idu'] = $eid;
		}

		$this->notificarRecarga($post);
	}

	public function actualizar()
	{
		$post = Input::post();

		if (empty($post['idu']) && !empty($post['elementos_idu'])) $post['idu'] = $post['elementos_idu'];
		if (empty($post['elementos_idu']) && !empty($post['idu'])) $post['elementos_idu'] = $post['idu'];

		// Guardamos, pero NO confiamos en el retorno de UPDATE (bool)
		if ($post['aplicar_en'] === 'aventura' || $post['aplicar_en'] === 'ambas') {
			(new Aventuras_elementos)->salvar($post);
		}
		if ($post['aplicar_en'] === 'partida' || $post['aplicar_en'] === 'ambas') {
			(new Partidas_elementos)->salvar($post);
		}

		// UID efectivo: siempre del POST en updates
		$eid = !empty($post['elementos_idu']) ? (string)$post['elementos_idu']
			: (!empty($post['idu']) ? (string)$post['idu'] : '');

		if ($eid !== '') {
			$post['elementos_idu'] = $eid;
			$post['idu'] = $eid;
		}

		$this->notificarRecarga($post);
	}

	public function eliminar()
	{
		$post = Input::post();
		$eid = !empty($post['elementos_idu']) ? $post['elementos_idu'] : $post['idu'];

		if ($post['aplicar_en'] === 'aventura' || $post['aplicar_en'] === 'ambas') {
			(new Aventuras_elementos)->eliminar($post);
		}
		if ($post['aplicar_en'] === 'partida' || $post['aplicar_en'] === 'ambas') {
			(new Partidas_elementos)->eliminar($eid);
		}

		$this->notificarRecarga($post);
	}

	public function posicion_partida($elementos_idu, $x, $y)
	{
		(new Partidas_elementos)->guardarPosicion($elementos_idu, $x, $y);
		View::select(null);
	}

	/* Interno */
	protected function notificarRecarga($post)
	{
		$pid = isset($post['partidas_idu']) ? (string)$post['partidas_idu'] : '';
		$eid = !empty($post['elementos_idu']) ? (string)$post['elementos_idu'] : (!empty($post['idu']) ? (string)$post['idu'] : '');

		if ($pid === '') return;

		/* Máster (panel): unitario si hay $eid; si no, lista */
		if ($eid !== '') {
			$panelUrl = '/ev/panel/recibir_elemento/' . $pid . '/' . $eid;
			_url::enviarAlCanal('dj_elementos_' . $pid, [
				'url' => $panelUrl,
				'append' => true
			]);
			$this->traceAjax('NOTIFY dj_elementos_' . $pid . ' ⇒ ' . $panelUrl . ' (append=1)');
		} else {
			$panelUrl = '/ev/panel/recibir_elementos/' . $pid;
			_url::enviarAlCanal('dj_elementos_' . $pid, [
				'url' => $panelUrl,
				'append' => false
			]);
			$this->traceAjax('NOTIFY dj_elementos_' . $pid . ' ⇒ ' . $panelUrl . ' (append=0)');
		}

		/* Jugadores */
		if ($eid !== '') {
			$playUrl = '/ev/jugar/recibir_elemento/' . $eid;
			_url::enviarAlCanal('pj_elementos_' . $pid, [ 'url' => $playUrl ]);
			$this->traceAjax('NOTIFY pj_elementos_' . $pid . ' ⇒ ' . $playUrl);
		} else {
			$playUrl = '/ev/jugar/recibir_elementos/' . $pid;
			_url::enviarAlCanal('pj_elementos_' . $pid, [ 'url' => $playUrl ]);
			$this->traceAjax('NOTIFY pj_elementos_' . $pid . ' ⇒ ' . $playUrl);
		}
	}

	protected function traceAjax($msg)
	{
		if (Input::isAjax()) {
			echo "\n<!-- " . h($msg) . " -->\n";
		}
	}
}
