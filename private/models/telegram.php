<?php
/**
 * Envío de publicaciones a Telegram
 */
class Telegram
{
	// Chats destino
	private const CHAT_BOT_1 = '-1001114071073';
	private const CHAT_BOT_2 = '-1001442242098';

	// Límite de texto de Telegram
	private const MAX_TEXT = 4096;

	// Reintentos ante errores transitorios de red
	private const RETRIES = 3;
	private const BACKOFF_MS = 300;

	/**
	 * @param int   $bot  1 o 2 (selecciona chat_id y token)
	 * @param array $list ['titulo','contenido','slug','apodo','usuarios_idu','fotos','enlace'...]
	 * @return array|false|null
	 */
	public function send($bot, $list)
	{
		if (_server::isLocal()) {
			return false;
		}

		$bot = (int)$bot;
		$bot_token = Config::get('keys.telegram.bot'.$bot.'_token');
		if (!$bot_token) {
			_mail::toAdmin('Telegram-Bot::error', 'Falta bot_token para bot='.$bot);
			return null;
		}

		$chat_id = ($bot === 1) ? self::CHAT_BOT_1 : self::CHAT_BOT_2;

		$titulo = isset($list['titulo']) ? trim(str_replace('"', "'", (string)$list['titulo'])) : '';
		$contenido = isset($list['contenido']) ? trim(str_replace('"', "'", (string)$list['contenido'])) : '';
		$apodo = isset($list['apodo']) ? str_replace('"', "'", (string)$list['apodo']) : '';

		$slug = isset($list['slug']) ? (string)$list['slug'] : '';
		$url = rtrim((string)DOMAIN, '/').'/publicaciones/'.$slug;

		$text = '<b>'.$this->esc($titulo)."</b>\n"
			."➖\n"
			.'<i>'.$this->esc($contenido)."</i>\n"
			."➖\n"
			.'👤 <b>'.$this->esc($apodo)."</b>\n"
			.$this->esc($url);

		if (mb_strlen($text, 'UTF-8') > self::MAX_TEXT) {
			$text = $this->truncateUtf8($text, self::MAX_TEXT);
		}

		$params = [
			'chat_id' => $chat_id,
			'parse_mode' => 'HTML',
			'text' => $text,
			'disable_web_page_preview' => false,
		];

		$endpoint = 'https://api.telegram.org/bot'.$bot_token.'/sendMessage';
		$response = null;
		$lastErr = null;

		for ($i = 1; $i <= self::RETRIES; $i++) {
			try {
				$response = Kttp::get($endpoint)
					->query($params)
					->getJson();

				if (is_array($response) && !empty($response['ok'])) {
					_mail::toAdmin(
						'Telegram-Bot::ok',
						_var::return([
							'try' => $i,
							'data' => $params,
							'resp' => $response,
						])
					);
					return $response;
				}

				$lastErr = 'API error '.json_encode($response);
				break;
			} catch (\Throwable $e) {
				$lastErr = $e->getMessage();
				if ($i < self::RETRIES) {
					usleep(self::BACKOFF_MS * 1000);
				}
			}
		}

		_mail::toAdmin(
			'Telegram-Bot::fail',
			_var::return([
				'tries' => self::RETRIES,
				'data' => $params,
				'error' => $lastErr,
			])
		);

		return $response;
	}

	private function esc($s)
	{
		return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	private function truncateUtf8($s, $max)
	{
		if (mb_strlen($s, 'UTF-8') <= $max) {
			return $s;
		}
		$cut = mb_substr($s, 0, max(0, $max - 1), 'UTF-8');
		return $cut.'…';
	}
}
