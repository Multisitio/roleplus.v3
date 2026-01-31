<?php
/**
 * _openai con modo "determinista" para URL de YouTube /videos
 * ✦ Si el prompt contiene una URL de canal YouTube (/@, /channel/, /c/) y/o /videos:
 *   - Hace fetch directo de la página (cURL) y extrae el primer videoId de ytInitialData.
 *   - Devuelve el enlace real al último vídeo. Si falla, dice "No he encontrado fuentes fiables."
 * ✦ En otros casos: usa Responses API con web_search (fallback preview) y "no citations → no answer".
 */
class _openai
{
	static public function ask($prompt, $role = '', $name = '')
	{
		ini_set('max_execution_time', 120000);

		// 1) INTENTO DETERMINISTA: URL de YouTube canal / videos
		$url = self::firstUrl($prompt);
		if ($url && self::isYouTubeChannelUrl($url)) {
			// Normaliza a pestaña /videos
			$videosUrl = self::ensureVideosTab($url);
			$html = self::httpGet($videosUrl);
			if (is_string($html) && $html !== '') {
                $videoId = self::extractLatestYouTubeVideoId($html);
				if ($videoId) {
					// Devolvemos objeto estilo Responses con texto directo (sin LLM)
					$out = (object)[
						'output_text' => 'https://www.youtube.com/watch?v=' . $videoId,
						'_source' => 'deterministic_youtube_scrape',
						'_citations' => [$videosUrl]
					];
					return $out;
				}
			}
			// No rompas: responde sin inventar
			return (object)[
				'output_text' => 'No he encontrado fuentes fiables.',
				'_error' => 'youtube_parse_failed',
				'_citations' => [$videosUrl]
			];
		}

		// 2) RESPONSES API con búsqueda web y "no citations → no answer"
		$key = Config::get('keys.openai.token');
		$headers = [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $key
		];

		$instructions = 'Responde solo si puedes citar al menos 1 URL real. Usa la herramienta de búsqueda web. Si no hay fuentes, di: "No he encontrado fuentes fiables."';

		$input = [];
		if ($role) {
			$input[] = ['role' => 'system', 'content' => $role];
		}
		$input[] = ['role' => 'user', 'content' => $prompt];

		$req1 = [
			'model' => 'gpt-4.1-mini',
			'instructions' => $instructions,
			'input' => $input,
			'temperature' => 0,
			'max_output_tokens' => 1900,
			'tools' => [
				['type' => 'web_search']
			],
			'tool_choice' => ['type' => 'web_search']
		];
		$res1 = self::postJson('https://api.openai.com/v1/responses', $headers, $req1);
		if ($res1['ok']) {
			return self::enforceCitations($res1['json'], $res1['raw']);
		}

		$req2 = $req1;
		$req2['tools'] = [['type' => 'web_search_preview']];
		$req2['tool_choice'] = ['type' => 'web_search_preview'];
		$res2 = self::postJson('https://api.openai.com/v1/responses', $headers, $req2);
		if ($res2['ok']) {
			return self::enforceCitations($res2['json'], $res2['raw']);
		}

		return (object)[
			'output_text' => 'No he encontrado fuentes fiables (búsqueda web no disponible).',
			'_error' => 'search_unavailable'
		];
	}

	/* ✦✦ Helpers HTTP y parsing ✦✦ */

	private static function httpGet($url)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);
		curl_setopt($ch, CURLOPT_ENCODING, ''); // acepta gzip/deflate
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
			'Accept-Language: es-ES,es;q=0.9,en;q=0.8'
		]);
		$body = curl_exec($ch);
		curl_close($ch);
		return $body;
	}

	private static function firstUrl($text)
	{
		if (!is_string($text) || $text === '') return '';
		if (preg_match('#https?://\S+#', $text, $m)) {
			return rtrim($m[0], ').,;\'"');
		}
		return '';
	}

	private static function isYouTubeChannelUrl($url)
	{
		return (bool)preg_match('#^https?://(www\.)?youtube\.com/(?:@[^/]+|channel/[^/]+|c/[^/]+)(?:/videos)?#i', $url);
	}

	private static function ensureVideosTab($url)
	{
		if (preg_match('#/videos/?$#i', $url)) return $url;
		return rtrim($url, '/') . '/videos';
	}

	private static function extractLatestYouTubeVideoId($html)
	{
		// Localiza el JSON inicial ytInitialData
		// Casos típicos: "var ytInitialData = {...};" o "\"ytInitialData\": {...}"
		$json = '';
		if (preg_match('#var\s+ytInitialData\s*=\s*(\{.*?\});#s', $html, $m)) {
			$json = $m[1];
		} elseif (preg_match('#"ytInitialData"\s*:\s*(\{.*?\})[,<]#s', $html, $m)) {
			$json = $m[1];
		}
		if ($json === '') return '';

		// Decodifica JSON de forma segura
		$data = json_decode(self::relaxJson($json), true);
		if (!is_array($data)) return '';

		// Recorre recursivamente hasta encontrar videoRenderer.videoId
		return self::findFirstVideoId($data);
	}

	private static function relaxJson($json)
	{
		// Intenta arreglar comas colgantes y caracteres de control
		$json = preg_replace("#,\s*}#s", "}", $json);
		$json = preg_replace("#,\s*]#s", "]", $json);
		return $json;
	}

	private static function findFirstVideoId($node)
	{
        if (is_array($node)) {
			// Forma directa
			if (isset($node['videoRenderer']) && is_array($node['videoRenderer'])) {
				if (!empty($node['videoRenderer']['videoId']) && is_string($node['videoRenderer']['videoId'])) {
					return $node['videoRenderer']['videoId'];
				}
			}
			// Otras variantes (richItemRenderer → content → videoRenderer)
			if (isset($node['richItemRenderer']['content']['videoRenderer']['videoId'])) {
				$vid = $node['richItemRenderer']['content']['videoRenderer']['videoId'];
				if (is_string($vid) && $vid !== '') return $vid;
			}
			// Recursivo
			foreach ($node as $v) {
				$id = self::findFirstVideoId($v);
				if ($id) return $id;
			}
		}
		return '';
	}

	/* ✦✦ Responses API helpers ✦✦ */

	private static function postJson($url, $headers, $data)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);

		$raw = curl_exec($ch);
		$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if (curl_errno($ch)) {
			$raw .= ' Error:' . curl_error($ch);
		}
		curl_close($ch);

		$json = json_decode($raw);
		_mail::toAdmin('_openai::ask[http '.$http.']', '<pre>'.$raw);

		return [
			'ok' => ($http >= 200 && $http < 300 && $json),
			'http' => $http,
			'raw' => $raw,
			'json' => $json
		];
	}

	private static function enforceCitations($obj, $raw)
	{
		$text = '';
		if (isset($obj->output_text) && is_string($obj->output_text)) {
			$text = $obj->output_text;
		} elseif (isset($obj->output) && is_array($obj->output)) {
			$chunks = [];
			foreach ($obj->output as $item) {
				if (isset($item->content) && is_array($item->content)) {
					foreach ($item->content as $c) {
						if (isset($c->type) && $c->type === 'output_text' && isset($c->text)) {
							$chunks[] = (string)$c->text;
						}
					}
				}
			}
			$text = implode("\n", $chunks);
		}

		$urls = [];
		if (isset($obj->citations) && is_array($obj->citations)) {
			foreach ($obj->citations as $c) {
				if (isset($c->url) && is_string($c->url)) {
					$urls[] = $c->url;
				}
			}
		}
		if (empty($urls)) {
			if (preg_match_all('#https?://[^\s\)\]]+#i', (string)$raw, $m)) {
				$urls = array_values(array_unique($m[0]));
			}
		}

		if (empty($urls)) {
			return (object)[
				'output_text' => 'No he encontrado fuentes fiables.',
				'_error' => 'no_citations'
			];
		}

		$obj->_citations = array_values($urls);
		if (!isset($obj->output_text) || !is_string($obj->output_text)) {
			$obj->output_text = $text;
		}
		return $obj;
	}
}
