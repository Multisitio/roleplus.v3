<?php
/**
 */
class Manuales_reglas extends LiteRecord
{
	#
	public function actualizar($matriz)
	{
		$values[] = (string)$matriz['manuales_idu'];
		$values[] = (string)$matriz['manuales_reglas_idu'] ?: 'Ninguno';
		$values[] = empty($matriz['pagina_nueva']) ? 0 : 1;
		$values[] = (string)$matriz['idioma'] ?? 'ES';
		$values[] = (string)$matriz['peso'];
		$values[] = empty($matriz['nombre']) ? t('Pon un nombre') : (string)$matriz['nombre'];
		$values[] = empty($matriz['valor']) ? '' : $matriz['valor'];
		
		$desc = self::normalizarDescripcion($matriz['descripcion'] ?? '', $idu);
		$desc = self::balancearHtml($desc);
		$values[] = $desc;
		$values[] = _html::bbcode($desc);
		$values[] = empty($matriz['notas']) ? '' : $matriz['notas'];
        $values[] = empty($_FILES['fotos']['name'][0])
            ? empty($matriz['fotos']) ? '' : implode(',', $matriz['fotos'])
            /*: (new Archivos)->incluir($_FILES);*/
			: _file::saveFiles($_FILES['fotos'], 'img/usuarios/' . Session::get('idu'));
        $values[] = empty($_FILES['fotos']['name'][0])
            ? $matriz['fotos_usuarios_idu'] ?? '' 
            : Session::get('idu');
		$values[] = $idu = (string)$matriz['idu'];

		$sql = 'UPDATE manuales_reglas SET manuales_idu=?, manuales_reglas_idu=?, pagina_nueva=?, idioma=?, peso=?, nombre=?, valor=?, descripcion=?, descripcion_md=?, notas=?, fotos=?, fotos_usuarios_idu=? WHERE idu=?';
		$r = self::query($sql, $values);
		#_var::die([$sql, $values, $r]);
		return $idu;
	}

	#
	public function crear($matriz, $traducir='')
	{
		$manuales_idu = $matriz['manuales_idu'];
		$idioma = (string)($matriz['idioma'] ?? 'ES');
		
		// Determinar el peso correcto
		if (!empty($matriz['idu'])) {
			// Es una duplicación: colocar inmediatamente después de la original
			$original_peso = isset($matriz['peso']) && $matriz['peso'] !== '' ? intval($matriz['peso']) : 0;
			
			// Desplazar las reglas siguientes
			self::query(
				'UPDATE manuales_reglas SET peso = CAST(peso AS UNSIGNED) + 1 WHERE manuales_idu = ? AND idioma = ? AND CAST(peso AS UNSIGNED) > ?',
				[$manuales_idu, $idioma, $original_peso]
			);
			
			$peso = $original_peso + 1;
		} else {
			// Es una regla nueva: colocar al final
			$r = self::first(
				'SELECT MAX(CAST(peso AS UNSIGNED)) as max_peso FROM manuales_reglas WHERE manuales_idu = ? AND idioma = ?',
				[$manuales_idu, $idioma]
			);
			$max_peso = ($r && isset($r->max_peso)) ? intval($r->max_peso) : -1;
			$peso = $max_peso + 1;
		}

		$values[] = (string)$manuales_idu;
		$values[] = (string)$matriz['manuales_reglas_idu'];
		$values[] = empty($matriz['pagina_nueva']) ? 0 : 1;
		$values[] = $idioma;
		$values[] = (string)$peso;
		$values[] = empty($matriz['nombre']) ? t('Pon un nombre') : (string)$matriz['nombre'];
		$values[] = $idu = _str::uid();
		$values[] = empty($matriz['valor']) ? '' : $matriz['valor'];

		if ($matriz['traducir_al']) {
			$pregunta = "Traduce (con solo una respuesta, solo el texto sin modificar el HTML y conservando toda la estructura HTML, conservando las tabulaciones y saltos de línea y sin añadir más HTML) al idioma iso({$matriz['traducir_al']}) lo siguiente: " . trim($matriz['descripcion']);
			$traduccion = (new Respuestas)->preguntarAIa($pregunta);
			$traduccion = str_replace('<br />', '', $traduccion);
			$matriz['descripcion'] = trim($traduccion);
		}

		$desc = self::normalizarDescripcion($matriz['descripcion'] ?? '', $idu);
		$desc = self::balancearHtml($desc);
		$values[] = $desc;
		$values[] = _html::bbcode($desc);
		$values[] = empty($matriz['notas']) ? '' : $matriz['notas'];
        $values[] = empty($_FILES['fotos']['name'][0])
            ? empty($matriz['fotos']) ? '' : implode(',', $matriz['fotos'])
            /*: (new Archivos)->incluir($_FILES);*/
			: _file::saveFiles($_FILES['fotos'], 'img/usuarios/' . Session::get('idu'));
        $values[] = empty($_FILES['fotos']['name'][0])
            ? $matriz['fotos_usuarios_idu']
            : Session::get('idu');

		$sql = 'INSERT INTO manuales_reglas SET manuales_idu=?, manuales_reglas_idu=?, pagina_nueva=?, idioma=?, peso=?, nombre=?, idu=?, valor=?, descripcion=?, descripcion_md=?, notas=?, fotos=?, fotos_usuarios_idu=?';
		#_::d([$sql, $values]);
		self::query($sql, $values);
		return $idu;
	}

	#
	public function eliminar($idu)
	{
		$sql = 'DELETE FROM manuales_reglas WHERE idu=?';
		self::query($sql, [$idu]);
	}

	#
	public function todas($manuales_idu, $idioma='ES')
	{
		$sql = 'SELECT * FROM manuales_reglas WHERE manuales_idu=? AND idioma=? ORDER BY CAST(peso AS UNSIGNED), nombre';
		$todas = self::all($sql, [$manuales_idu, $idioma]);
		$res = [];
		foreach ($todas as $una) {
			$res[$una->idu] = $una;
		}
		return $res;
	}

	#
	public function ordenar($orden)
	{
		if (!is_array($orden)) return;
		foreach ($orden as $peso => $idu) {
			self::query('UPDATE manuales_reglas SET peso=? WHERE idu=?', [$peso, $idu]);
		}
	}

	#
	public function una($idu='')
	{
		$sql = 'SELECT * FROM manuales_reglas WHERE idu=?';
		$una = self::first($sql, [$idu]);
		return $una ? $una : parent::cols();
	}

	/**
	 * Normaliza la descripción de la regla para evitar <article> duplicados o anidados,
	 * asegurando que el contenido neto esté envuelto en un único <article id="pag-{idu}">.
	 */
	public static function normalizarDescripcion($desc, $idu)
	{
		$desc = trim($desc ?? '');
		
		// Si ya está envuelto en <article> en sus extremos, no hacemos nada
		if (preg_match('/^<article\b[^>]*>.*<\/article>$/is', $desc)) {
			return $desc;
		}

		// Si no, lo envolvemos una única vez
		$id_attr = $idu ? ' id="pag-' . $idu . '"' : '';
		return "<article{$id_attr}>\n{$desc}\n</article>";
	}

	/**
	 * Balancea etiquetas HTML y cierra comillas huérfanas en los atributos.
	 */
	public static function balancearHtml($html)
	{
		if (trim($html) === '') {
			return '';
		}

		// Clean JavaScript from the HTML
		// 1. Remove script tags
		$html = preg_replace('/<script\b[^>]*>([\s\S]*?)<\/script>/i', '', $html);
		// 2. Remove on* event handlers (quoted and unquoted)
		$html = preg_replace('/\s+on[a-zA-Z]+\s*=\s*(["\']).*?\1/i', '', $html);
		$html = preg_replace('/\s+on[a-zA-Z]+\s*=\s*[^\s>]+/i', '', $html);
		// 3. Remove javascript: links/uris
		$html = preg_replace('/\s+(href|src|data)\s*=\s*(["\'])\s*javascript:.*?\2/i', '', $html);

		// Balance and format HTML using DOMDocument and a recursive walker
		$dom = new DOMDocument();
		$dom->preserveWhiteSpace = true;
		$dom->formatOutput = false;
		libxml_use_internal_errors(true);
		
		$loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();

		if ($loaded && $dom->documentElement) {
			// Formateo automático de href de los enlaces
			$links = $dom->getElementsByTagName('a');
			foreach ($links as $link) {
				$href = trim($link->getAttribute('href'));
				$text = trim($link->textContent);

				$textForSlug = '';
				foreach ($link->childNodes as $childNode) {
					if ($childNode->nodeType === XML_ELEMENT_NODE && strtolower($childNode->nodeName) === 'span') {
						continue;
					}
					$textForSlug .= $childNode->textContent;
				}
				$textForSlug = trim($textForSlug);

				$isUrl = false;
				if (preg_match('/^https?:\/\//i', $href)) {
					$isUrl = true;
				} elseif (preg_match('/^www\./i', $href)) {
					$href = 'https://' . $href;
					$isUrl = true;
				} elseif (preg_match('/^https?:\/\//i', $text)) {
					$href = $text;
					$isUrl = true;
				} elseif (preg_match('/^www\./i', $text)) {
					$href = 'https://' . $text;
					$isUrl = true;
				}

				if ($isUrl) {
					$link->setAttribute('href', $href);
					$link->setAttribute('target', '_blank');
					$link->setAttribute('rel', 'noopener noreferrer');
				} else {
					if ($textForSlug !== '') {
						$slug = _url::slug($textForSlug);
						$link->setAttribute('href', '#' . $slug);
					}
					if ($link->hasAttribute('target')) {
						$link->removeAttribute('target');
					}
					if ($link->hasAttribute('rel')) {
						$link->removeAttribute('rel');
					}
				}
			}

			$html = self::nodeToHtml($dom->documentElement, "");
		}

		return html_entity_decode($html, ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Formatea recursivamente un nodo DOM en HTML estructurado con tabulaciones.
	 */
	public static function nodeToHtml($node, $indent = "")
	{
		$tab = "\t";
		
		if ($node instanceof DOMText) {
			$text = $node->nodeValue;
			// Normalizar secuencias de espacios/saltos de línea a un único espacio
			$text = preg_replace('/\s+/', ' ', $text);
			return $text;
		}
		
		if ($node instanceof DOMComment) {
			return $indent . "<!--" . $node->nodeValue . "-->\n";
		}
		
		if ($node instanceof DOMElement) {
			$tag = strtolower($node->tagName);
			
			$attrs = "";
			foreach ($node->attributes as $attr) {
				$attrs .= " " . $attr->name . '="' . htmlspecialchars($attr->value, ENT_QUOTES, 'UTF-8') . '"';
			}
			
			$selfClosing = ['br', 'img', 'hr', 'input', 'meta', 'link'];
			if (in_array($tag, $selfClosing)) {
				return "<{$tag}{$attrs}>";
			}
			
			$isBlockContainer = in_array($tag, [
				'header', 'section', 'footer', 'article', 'div', 'nav', 'main', 
				'table', 'thead', 'tbody', 'tfoot', 'tr', 'ul', 'ol', 'form', 
				'fieldset', 'figure', 'blockquote'
			]);
			
			$isContentBlock = in_array($tag, [
				'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'td', 'th', 'legend', 'caption'
			]);
			
			$hasBlockChildren = false;
			foreach ($node->childNodes as $child) {
				if ($child instanceof DOMElement) {
					$childTag = strtolower($child->tagName);
					$isChildBlock = in_array($childTag, [
						'header', 'section', 'footer', 'article', 'div', 'nav', 'main', 
						'table', 'thead', 'tbody', 'tfoot', 'tr', 'ul', 'ol', 'form', 
						'fieldset', 'figure', 'blockquote', 'p', 'h1', 'h2', 'h3', 'h4', 
						'h5', 'h6', 'li', 'td', 'th', 'legend', 'caption'
					]);
					if ($isChildBlock) {
						$hasBlockChildren = true;
						break;
					}
				}
			}
			
			if (!$isBlockContainer && !$hasBlockChildren) {
				$content = "";
				foreach ($node->childNodes as $child) {
					$content .= self::nodeToHtml($child, "");
				}
				$content = trim(preg_replace('/\s+/', ' ', $content));
				return "<{$tag}{$attrs}>{$content}</{$tag}>";
			}
			
			$content = "";
			$childIndent = $indent . $tab;
			
			$lastChildWasBlock = true;
			foreach ($node->childNodes as $child) {
				$childHtml = self::nodeToHtml($child, $childIndent);
				
				if (trim($childHtml) === "") continue;
				
				$isChildElementBlock = false;
				if ($child instanceof DOMElement) {
					$childTag = strtolower($child->tagName);
					$isChildElementBlock = in_array($childTag, [
						'header', 'section', 'footer', 'article', 'div', 'nav', 'main', 
						'table', 'thead', 'tbody', 'tfoot', 'tr', 'ul', 'ol', 'form', 
						'fieldset', 'figure', 'blockquote', 'p', 'h1', 'h2', 'h3', 'h4', 
						'h5', 'h6', 'li', 'td', 'th', 'legend', 'caption'
					]);
					
					// Treat inline tags as block tags if they are direct children of a block container
					if (!$isChildElementBlock && $isBlockContainer && in_array($childTag, ['a', 'span', 'img'])) {
						$isChildElementBlock = true;
					}
				}
				
				if ($isChildElementBlock) {
					$content .= "\n" . $childIndent . trim($childHtml);
					$lastChildWasBlock = true;
				} else {
					if ($lastChildWasBlock) {
						$content .= "\n" . $childIndent . ltrim($childHtml);
					} else {
						$content .= $childHtml;
					}
					$lastChildWasBlock = false;
				}
			}
			
			if ($content !== "") {
				$content .= "\n" . $indent;
			}
			
			return "<{$tag}{$attrs}>{$content}</{$tag}>";
		}
		
		return "";
	}
}
