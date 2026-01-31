<?php
/**
 */
class Respuestas extends LiteRecord
{
	# 1
	public function responderPregunta($pregunta)
	{	
		$pregunta = trim($pregunta);

		if ( ! $pregunta) {
			return '';
		}

		if ( ! stristr($arr['pregunta'], 'openai')) {
			$respuestas = self::responderConLike($pregunta);
			if (is_array($respuestas) && count($respuestas) == 1) {
				Session::setArray('toast', t('Respuesta rápida.'));
				return $respuestas[0]->respuesta;
			}

			$respuesta = self::responderConMatch($pregunta);
			if ($respuesta) {
				Session::setArray('toast', t('Respuesta encontrada.'));
				return $respuesta->respuesta;
			}
		}

		$respuesta = self::responderConOpenAi($pregunta);
		Session::setArray('toast', t('Respuesta elaborada.'));

		return $respuesta;
	}

	# 1.1
	public function responderConLike($pregunta)
	{	
		$pregunta = trim($arr['pregunta']);
		$sql = 'SELECT respuesta FROM respuestas WHERE pregunta LIKE ?';

		return parent::all($sql, ["%$pregunta%"]);
	}

	# 1.2
	public function responderConMatch($pregunta)
	{	
		$sql = "SELECT *, MATCH (pregunta) AGAINST (? IN NATURAL LANGUAGE MODE) AS score
		FROM respuestas 
		WHERE MATCH (pregunta) AGAINST (? IN NATURAL LANGUAGE MODE)
		HAVING score > 4
		ORDER BY score DESC";

		return parent::first($sql, [$pregunta, $pregunta]);
	}
	
	# 1.3
	public function responderConOpenAi($pregunta)
	{
		$respuesta = self::preguntarAOpenAi($pregunta);

		$vals[] = Session::get('idu');
		$vals[] = $pregunta;
		$vals[] = _str::uid();
		$vals[] = $respuesta;
		$vals[] = date('Y-m-d H:i:s');
		$vals[] = date('Y-m-d H:i:s'); # falta sumar $time_end
		$vals[] = $time_end;

		$sql = 'INSERT INTO respuestas SET usuarios_idu=?, pregunta=?, idu=?, respuesta=?, preguntado=?, respondido=?, retardo=?';
		parent::query($sql, $vals);

		self::enviarDatosAlCorreoDelAdmin($vals);

		return $respuesta;
	}

	# 1.3.1
	public function preguntarAOpenAi($pregunta, $rol = '', $nombre = '')
	{
		$time_beg = microtime(1);
		$respuestas = _openai::ask($pregunta, $rol, $nombre);
		$time_end = microtime(1) - $time_beg;

		_mail::toAdmin('OpenAi responde', '<pre>' . print_r($respuestas, 1));

		$respuesta_txt = '';

		if ($respuestas) {
			// Responses API: campo de conveniencia
			if (isset($respuestas->output_text) && is_string($respuestas->output_text)) {
				$respuesta_txt = $respuestas->output_text;
			}
			// Fallback Chat Completions (message.content o text)
			else if (isset($respuestas->choices) && is_array($respuestas->choices)) {
				$chunks = [];
				foreach ($respuestas->choices as $res) {
					if (isset($res->message) && isset($res->message->content) && is_string($res->message->content)) {
						$chunks[] = $res->message->content;
					} else if (isset($res->text) && is_string($res->text)) {
						$chunks[] = trim($res->text);
					}
				}
				$respuesta_txt = implode("\n---\n", $chunks);
			}
			// Responses API sin output_text (poco común): intentar extraer texto de output[]
			else if (isset($respuestas->output) && is_array($respuestas->output)) {
				$chunks = [];
				foreach ($respuestas->output as $item) {
					if (isset($item->content) && is_array($item->content)) {
						foreach ($item->content as $c) {
							if (isset($c->type) && $c->type === 'output_text' && isset($c->text) && is_string($c->text)) {
								$chunks[] = $c->text;
							}
						}
					}
				}
				$respuesta_txt = implode("\n", $chunks);
			}
		}

		// Normaliza y limpia
		$respuesta_txt = (string)$respuesta_txt;
		$respuesta_txt = str_replace(["\r\n", "\r"], "\n", $respuesta_txt);
		$respuesta_txt = _html::stripTags($respuesta_txt);

		return $respuesta_txt;
	}

	# 1.3.2
	public function enviarDatosAlCorreoDelAdmin($vals)
	{	
		/*$body = $pregunta;
		$body .= "\n\nhttps://roleplus.app/atalaya/ia/aprender/$idu";*/

		_mail::toAdmin('OpenAi responde a un usuario', '<pre>'.print_r($vals, 1));
	}

	# 2
	public function todas()
	{
		$sql = 'SELECT * FROM respuestas ORDER BY pregunta';
		return parent::all($sql);
	}

	# 3
	public function una($idu)
	{
		if ( ! $idu) {
			return parent::cols();
		}
		$sql = 'SELECT * FROM respuestas WHERE idu=?';
		return parent::first($sql, [$idu]);
	}

	# 4
	public function crear($post)
	{	
		$vals[] = $idu = _str::uid();
		$vals[] = Session::get('idu');
		$vals[] = $post['pregunta'];
		$vals[] = $post['respuesta'];
		$vals[] = date('Y-m-d H:i:s');
		$vals[] = date('Y-m-d H:i:s');
		$vals[] = 0;
		$sql = 'INSERT INTO respuestas SET idu=?, usuarios_idu=?, pregunta=?, respuesta=?, preguntado=?, respondido=?, retardo=?';
		parent::query($sql, $vals);
		Session::setArray('toast', t('Respuesta creada.'));
		return $idu;
	}

	# 5
	public function actualizar($post)
	{	
		$vals[] = Session::get('idu');
		$vals[] = $post['pregunta'];
		$vals[] = $post['respuesta'];
		$vals[] = date('Y-m-d H:i:s');
		$vals[] = date('Y-m-d H:i:s');
		$vals[] = 0;
		$vals[] = $idu = $post['idu'];
		$sql = 'UPDATE respuestas SET usuarios_idu=?, pregunta=?, respuesta=?, preguntado=?, respondido=?, retardo=? WHERE idu=?';
		parent::query($sql, $vals);
		Session::setArray('toast', t('Respuesta actualizada.'));
		return $idu;
	}

	# 6
	public function eliminar($idu)
	{	
		$sql = 'DELETE FROM respuestas WHERE idu=?';
		parent::query($sql, [$idu]);

		Session::setArray('toast', t('Respuesta eliminada.'));
	}
}
