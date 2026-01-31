<?php
/**
 * Utilidades de URL y emision de eventos NO BLOQUEANTE.
 * ✦ Emision (por defecto): POST JSON a /pub en 127.0.0.1:8383 (SSE) y 127.0.0.1:8385 (WS-HTTP).
 * ✦ Contrato /pub: { ch: "IDU", url: "/ruta", box?: ".selector", append?: bool }
 * ✦ Logs: sin '@'; error_log() solo en fallos relevantes.
 * ✦ Seguridad: solo se permiten URLs relativas (no http/https/javascript).
 */
class _url
{
	// -------------------- Config (ajustable) --------------------
	private const EMIT_HOST = '127.0.0.1';
	private const EMIT_PORT_SSE = 8383;
	private const EMIT_PORT_WS  = 8385;
	private const EMIT_PATH = '/pub';

	private const EMIT_CONNECT_TIMEOUT_S = 0.05;	// 50 ms
	private const EMIT_WRITE_TIMEOUT_US = 200000;	// 0.2 s (best-effort)

	private const EMIT_MAX_CANAL_LEN = 96;
	private const EMIT_MAX_URL_LEN = 2048;
	private const EMIT_MAX_BOX_LEN = 160;

	/* ------------------------------------------------------------
	 * Normalizacion basica para generar slugs (quita rarezas).
	 * ------------------------------------------------------------ */
	private static function normalize(string $s): string
	{
		$orig = $s;
		if (function_exists('iconv')) {
			$tmp = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
			if ($tmp !== false) {
				$s = $tmp;
			}
		}
		$s = preg_replace('/[^\pL\d\s_-]+/u', ' ', $s ?? '') ?? '';
		$s = trim($s);
		return $s === '' ? $orig : $s;
	}

	public static function slug($url, $by = '-')
	{
		$url = self::normalize((string)$url);
		$url = preg_replace('/[^\pL\d]+/u', $by, $url);
		$url = preg_replace("/[^$by\w]+/", '', $url);
		$url = trim($url, $by);
		$url = preg_replace("/$by+/", $by, $url);
		$url = mb_strtolower($url);
		return $url === '' ? 'n-a' : $url;
	}

	public static function sinParametros($url)
	{
		if (strstr($url, '?')) {
			return $url;
		}
		return explode('?', (string)$url, 2)[0];
	}

	public static function delDominio($url)
	{
		if (empty($url)) {
			return '';
		}
		if (strpos($url, '://') === false) {
			return $url;
		}
		$parts = parse_url($url);
		if (!$parts) {
			return $url;
		}
		$path = $parts['path'] ?? '/';
		$q = isset($parts['query']) ? '?' . $parts['query'] : '';
		return $path . $q;
	}

	public static function actual()
	{
		$schema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
		$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
		$uri = $_SERVER['REQUEST_URI'] ?? '/';
		return $schema . $host . $uri;
	}

	public static function redireccionar($url, $codigo = 302)
	{
		header('Location: ' . $url, true, $codigo);
		exit;
	}

	public static function usarReferer()
	{
		if (empty($_SERVER['HTTP_REFERER'])) {
			return false;
		}
		$rel = self::sinParametros($_SERVER['HTTP_REFERER']);
		return self::delDominio($rel);
	}

	public static function enviarAlCanal(string $canal, $url): bool
	{
		// Evita ruido en local/dev.
		if (_server::isLocal()) {
			return false;
		}

		// --- legacy mixto (array|string) ---
		$box = null;
		$append = null;
		if (is_array($url)) {
			$box = isset($url['box']) ? (string)$url['box'] : null;
			$append = array_key_exists('append', $url) ? (bool)$url['append'] : null;
			$url = isset($url['url']) ? (string)$url['url'] : '';
		} else {
			$url = (string)$url;
		}

		/* Reglas minimas y seguridad:
		 * - requiere idu de sesion (evita emitir desde visitantes/bots)
		 * - canal y url no vacios
		 * - url relativa segura (no http/https/javascript)
		 * - limites de longitud (baratos, anti abuso)
		 */
		if (!Session::get('idu') || $canal === '' || $url === '') {
			return false;
		}
		if (strlen($canal) > self::EMIT_MAX_CANAL_LEN || strlen($url) > self::EMIT_MAX_URL_LEN) {
			error_log('enviarAlCanal longitudes no validas');
			return false;
		}

		$u = ltrim($url, " \t");
		if (stripos($u, 'http://') === 0 || stripos($u, 'https://') === 0 || stripos($u, 'javascript:') === 0) {
			error_log('enviarAlCanal url no valida: ' . $url);
			return false;
		}

		if ($box !== null && $box !== '' && strlen($box) > self::EMIT_MAX_BOX_LEN) {
			$box = substr($box, 0, self::EMIT_MAX_BOX_LEN);
		}

		// Payload ESTRICTO (claves esperadas por /pub: ch,url,box,append)
		$payload = ['ch' => $canal, 'url' => $url];
		if ($box !== null && $box !== '') {
			$payload['box'] = $box;
		}
		if ($append !== null) {
			$payload['append'] = (bool)$append;
		}

		$json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if (!is_string($json) || $json === '') {
			error_log('enviarAlCanal json_encode fallo');
			return false;
		}

		/* Fire-and-forget:
		 * - conecta rapido
		 * - write best-effort no bloqueante
		 * - no lee respuesta
		 * - siempre cierra
		 */
		$fire = static function (string $host, int $port, string $path, string $body): void {
			$errno = 0;
			$errstr = '';
			$fp = fsockopen($host, $port, $errno, $errstr, self::EMIT_CONNECT_TIMEOUT_S);
			if (!$fp) {
				return;
			}

			stream_set_blocking($fp, false);
			stream_set_timeout($fp, 0, self::EMIT_WRITE_TIMEOUT_US);

			$req =
				"POST " . $path . " HTTP/1.1\r\n" .
				"Host: " . $host . "\r\n" .
				"Content-Type: application/json\r\n" .
				"Content-Length: " . strlen($body) . "\r\n" .
				"Connection: close\r\n" .
				"X-Requested-With: XMLHttpRequest\r\n\r\n" .
				$body;

			fwrite($fp, $req);
			fclose($fp);
		};

		$fire(self::EMIT_HOST, self::EMIT_PORT_SSE, self::EMIT_PATH, $json); // SSE
		$fire(self::EMIT_HOST, self::EMIT_PORT_WS, self::EMIT_PATH, $json);  // WS-HTTP

		return true;
	}
}
