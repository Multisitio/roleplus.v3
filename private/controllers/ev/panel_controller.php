<?php
/**
 * Controlador: ev/panel
 * Mantiene firmas y rutas existentes. Sin dependencias nuevas.
 */
class PanelController extends EvController
{
	/* ===== Config arriba ===== */
	private const LOG_CH = 'ev_panel';
	private const OK_EMPTY = '';

	/* ===== Filtros ===== */
	protected function before_filter()
	{
		if ($action = Input::post('action')) {
			unset($_POST['action']);
			if (in_array($action, ['crear','actualizar','eliminar','duplicar'], true)) {
                $this->$action();
                return;
            }
        }
        Input::isAjax() ? View::template('ajax') : View::template('master');
	}

	public function conectado($partidas_idu)
	{
		(new Partidas_usuarios)->conectar($partidas_idu);
		View::select(null, null);
		exit;
	}

	/* ===== Panel: maestro ===== */
	public function partida($partidas_idu)
	{
		(new Partidas_usuarios)->conectar($partidas_idu);
		$this->conectados = (new Partidas_usuarios)->conectados($partidas_idu);
		$this->partida = (new Eventos)->uno($partidas_idu);
		$this->aventura = (new Aventuras)->una($this->partida->aventuras_idu);
		$this->ficha = (new Fichas)->una($this->aventura->fichas_idu);
		$this->personajes = (new Personajes)->todos($this->ficha->idu);
		$this->personajes_usuarios = (new Personajes_usuarios)->todos($this->ficha->idu);
		$this->elementos = (new Partidas_elementos)->todos($partidas_idu);
		$this->elementos_tipos = Config::get('ev.elementos_tipos');
		$this->fondo = (new Partidas_elementos)->obtenerFondo($partidas_idu);
		$this->mensajes = (new Conversaciones_mensajes)->todos(['conversaciones_idu' => $partidas_idu]);
		$this->add_to = '.chat textarea';
		$this->panel = 'partida';
		View::select('elementos');
	}

	public function aventura($partidas_idu)
	{
		(new Partidas_usuarios)->conectar($partidas_idu);
		$this->conectados = (new Partidas_usuarios)->conectados($partidas_idu);
		$this->partida = (new Eventos)->uno($partidas_idu);
		$this->aventura = (new Aventuras)->una($this->partida->aventuras_idu);
		$this->ficha = (new Fichas)->una($this->aventura->fichas_idu);
		$this->personajes = (new Personajes)->todos($this->ficha->idu);
		$this->personajes_usuarios = (new Personajes_usuarios)->todos($this->ficha->idu);
		$this->elementos = (new Aventuras_elementos)->todos($this->partida->aventuras_idu);
		$this->elementos_tipos = Config::get('ev.elementos_tipos');
		$this->fondo = (new Partidas_elementos)->obtenerFondo($partidas_idu);
		$this->mensajes = (new Conversaciones_mensajes)->todos(['conversaciones_idu' => $partidas_idu]);
		$this->add_to = '.chat textarea';
		$this->panel = 'aventura';
		View::select('aventura');
	}

	/* ===== Envíos SSE/WS ===== */
	public function enviar_elemento($partidas_idu, $elementos_idu)
	{
		(new Partidas_elementos)->mostrarElemento($elementos_idu);
		_url::enviarAlCanal('pj_elementos_' . $partidas_idu, [
			'url' => '/ev/jugar/recibir_elemento/' . $elementos_idu
		]);
		View::select(null, null);
	}

	public function enviar_elementos($partidas_idu, $elementos_idu)
	{
		(new Partidas_elementos)->mostrarElemento($elementos_idu);
		_url::enviarAlCanal('pj_elementos_' . $partidas_idu, [
			'url' => '/ev/jugar/recibir_elementos/' . $partidas_idu
		]);
		View::select(null, null);
	}

	public function recibir_elemento($partidas_idu, $elementos_idu)
	{
		$this->partida = (new Eventos)->uno($partidas_idu);
		$this->ele = (new Partidas_elementos)->uno($elementos_idu);
		$this->eliminar = (string)$elementos_idu;
		View::select('elemento');
	}

	public function recibir_elementos($partidas_idu)
	{
		$this->partida = (new Eventos)->uno($partidas_idu);
		$this->elementos = (new Partidas_elementos)->todos($partidas_idu);
		View::select('elementos');
	}

	public function enviar_fondo($partidas_idu, $elementos_idu)
	{
		$cat['partidas_idu'] = $partidas_idu;
		$cat['elementos_idu'] = $elementos_idu;
		$cat['fondo'] = 1;
		(new Partidas_elementos)->establecerFondo($cat);

		_url::enviarAlCanal('fondo_' . $partidas_idu, [
			'url' => '/ev/jugar/recibir_fondo/' . $partidas_idu
		]);
		View::select(null, null);
	}

	/* ===== CRUD vía POST (desde ventana.phtml) ===== */
	public function crear()      { $this->procesarElemento('crear'); }
	public function actualizar() { $this->procesarElemento('actualizar'); }

	public function duplicar()
	{
		$post = Input::post();
		unset($post['elementos_idu']);
		$this->procesarElemento('crear', $post);
	}

	public function eliminar()
	{
		$post = Input::post();
		$eid = (string)($post['elementos_idu'] ?? '');
		$pid = (string)($post['partidas_idu'] ?? '');

		if ($post['aplicar_en'] === 'aventura' || $post['aplicar_en'] === 'ambas') {
			(new Aventuras_elementos)->eliminar($post);
		}
		if ($post['aplicar_en'] === 'partida' || $post['aplicar_en'] === 'ambas') {
			(new Partidas_elementos)->eliminar($eid);
		}

		$this->responderAjax([
			'remove_id' => $eid,
			'close_modal' => true,
			'toast' => t('Elemento eliminado.')
		]);

		$this->notificarDiff($pid, $eid);
	}

	/* ===== Privados ===== */
	private function procesarElemento(string $modo, array $post = null)
	{
		$post = $post ?? Input::post();

		$post['aplicar_en']    = $post['aplicar_en']    ?? 'partida';
		$post['partidas_idu']  = $post['partidas_idu']  ?? '';
		$post['elementos_idu'] = $post['elementos_idu'] ?? '';
		$post['enviado']       = !empty($post['enviado']) ? 1 : 0;

		$aplicar = $post['aplicar_en'];
		$pid = (string)$post['partidas_idu'];
		$eid = (string)$post['elementos_idu'];

		if ($aplicar === 'aventura' || $aplicar === 'ambas') {
			$eid = (new Aventuras_elementos)->salvar($post);
		}
		if ($aplicar === 'partida' || $aplicar === 'ambas') {
			$eid = (new Partidas_elementos)->salvar($post);
		}

		$html = $this->renderElemento($pid, $eid);
		$this->responderAjax([
			'html' => $html,
			'replace_id' => $eid,
			'close_modal' => true,
			'toast' => $modo === 'crear' ? t('Elemento creado.') : t('Elemento actualizado.')
		]);

		/* ——— Antes: crear ⇒ notificarRecarga; ahora: siempre notificarDiff ——— */
		$this->notificarDiff($pid, $eid);
	}

	private function renderElemento(string $pid, string $eid): string
	{
		$ele = (new Partidas_elementos)->uno($eid);
		if (!$ele) return '';

		$view = new View;
		$view->setPath('ev/panel');
		$view->ele = $ele;
		$view->partida = (new Eventos)->uno($pid);
		$view->img_uid = Session::get('img_uid') ?? '';
		return $view->render('elemento');
	}

	private function responderAjax(array $data)
	{
		if (!Input::isAjax()) return;

		$out = '';

		if (!empty($data['html'])) {
			$id = $data['replace_id'] ?? uniqid('ele_');
			$out .= '<div data-id="' . h($id) . '">' . $data['html'] . '</div>';
			if (!empty($data['replace_id'])) {
				$out .= '<span data-remove_id="' . h($data['replace_id']) . '"></span>';
			}
		}

		if (!empty($data['remove_id'])) {
			$out .= '<span data-remove_id="' . h($data['remove_id']) . '"></span>';
		}

		if (!empty($data['close_modal'])) {
			$out .= '<span data-close></span>';
		}

		if (!empty($data['toast'])) {
			$out .= '<script>parent.toast && parent.toast("' . addslashes($data['toast']) . '")</script>';
		}

		echo $out;
		exit;
	}

	private function notificarDiff(string $pid, string $eid)
	{
		if (!$pid || !$eid) {
			$this->notificarRecarga($pid);
			return;
		}

		_url::enviarAlCanal('dj_elementos_' . $pid, [
			'url' => '/ev/panel/recibir_elemento/' . $pid . '/' . $eid
		]);
		_url::enviarAlCanal('pj_elementos_' . $pid, [
			'url' => '/ev/jugar/recibir_elemento/' . $eid
		]);
	}

	private function notificarRecarga(string $pid)
	{
		if (!$pid) return;

		_url::enviarAlCanal('dj_elementos_' . $pid, [
			'url' => '/ev/panel/recibir_elementos/' . $pid
		]);
		_url::enviarAlCanal('pj_elementos_' . $pid, [
			'url' => '/ev/jugar/recibir_elementos/' . $pid
		]);
	}

	public function posicion_partida($elementos_idu)
	{
		if (!Input::isAjax()) return;

		$x = (int)(Input::post('x') ?? 0);
		$y = (int)(Input::post('y') ?? 0);
		(new Partidas_elementos)->actualizarPosicion($elementos_idu, $x, $y);
		echo self::OK_EMPTY;
		exit;
	}

	/* ===== Log seguro (sin dependencias) ===== */
	private function logEx(string $where, Throwable $e, array $ctx = [])
	{
		$line = '[' . $where . '] ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine();
		if (!empty($ctx)) $line .= ' | ctx=' . json_encode($ctx);
		if (class_exists('Logger')) {
			try { Logger::error(self::LOG_CH . ': ' . $line); return; } catch (Throwable $ignore) {}
		}
		error_log(self::LOG_CH . ': ' . $line);
	}
}
