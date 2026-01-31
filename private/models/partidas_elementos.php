<?php
/**
 * Modelo: Elementos en partida (copia mutable de aventura)
 * Tabla: partidas_elementos
 * UID: idu → elementos_idu
 * Notas:
 * ✦ Seguridad: todos los UPDATE/DELETE/MODIFY filtran por usuarios_idu.
 * ✦ Compat: "imagenes" del form legacy se mapea a 'fotos'.
 * ✦ Mensajes: si llega $data['mensaje'], se vuelca a 'texto'.
 */
class Partidas_elementos extends LiteRecord
{
	/* =====================
	 * CONFIGURACIÓN
	 * ===================== */
	private const TABLE = 'partidas_elementos';
	private const FORMATO_ELEMENTO = 'elemento';
	private const FORMATO_MENSAJE = 'mensaje';
	private const DEFAULT_POS = 115;

	private const MEDIA_FIELDS = [
		'fotos' => '/\.(gif|jpe?g|png|svgz?|webp)$/i',
		'mp3' => '/\.mp3$/i',
		'mp4' => '/\.mp4$/i'
	];

	/* =====================
	 * UTILIDADES PRIVADAS
	 * ===================== */
	private function now(): string
	{
		return date('Y-m-d H:i:s');
	}

	private function uid(): string
	{
		return _str::uid();
	}

	private function userId(): string
	{
		return (string)Session::get('idu');
	}

	private function safeIntDef($value, int $default = 0): int
	{
		if ($value === null || $value === '') return $default;
		return is_numeric($value) ? (int)$value : $default;
	}

	private function ensureUid(array &$data): void
	{
		if (empty($data['elementos_idu'])) {
			$data['elementos_idu'] = $this->uid();
		}
	}

	/* Normaliza entrada POST sin romper legacy ("idu"/"imagenes") */
	private function normalize(array $input): array
	{
		$defaults = [
			'elementos_idu' => '',
			'partidas_idu' => '',
			'peso' => '0',
			'nombre' => 'Sin nombre',
			'tipo' => 'texto',
			'texto' => '',
			'fotos' => '',
			'mp3' => '',
			'mp4' => '',
			'youtube' => '',
			'enviado' => 0,
			'posicion_x' => '',
			'posicion_y' => ''
		];

		$data = array_merge($defaults, array_intersect_key($input, $defaults));

		if (!empty($input['idu'])) {
			$data['elementos_idu'] = (string)$input['idu'];
		}

		if (!empty($input['imagenes']) && empty($data['fotos'])) {
			$data['fotos'] = (string)$input['imagenes'];
		}

		$data['youtube'] = !empty($input['youtube']) ? _var::getUrlVar($input['youtube']) : '';
		$data['enviado'] = !empty($input['enviado']) ? 1 : 0;

		if (!empty($input['mensaje']) && empty($data['texto'])) {
			$data['texto'] = (string)$input['mensaje'];
		}

		return $data;
	}

	private function mergePreviousMedia(array &$data, ?object $prev): void
	{
		if (!$prev) return;

		foreach (self::MEDIA_FIELDS as $field => $_) {
			$flag = "_uploaded_$field";
			if (empty($data[$flag]) && empty($data[$field])) {
				$data[$field] = $prev->{$field} ?? '';
			}
		}
	}

	private function processUploads(array &$data): void
	{
		$uploaded = array_fill_keys(['fotos', 'mp3', 'mp4'], false);

		$files = (new Archivos)->incluir($_FILES) ?? [];
		$files = is_array($files) ? $files : ($files ? [$files] : []);
		$files = array_merge($files, $this->extractFileNames($_FILES));

		foreach ($files as $filename) {
			$filename = trim((string)$filename);
			if (!$filename) continue;

			foreach (self::MEDIA_FIELDS as $field => $pattern) {
				if (!$uploaded[$field] && preg_match($pattern, $filename)) {
					$data[$field] = $filename;
					$uploaded[$field] = true;
					break;
				}
			}
		}

		foreach ($uploaded as $field => $was) {
			$data["_uploaded_$field"] = $was;
		}

		$this->toastOnUpload($uploaded, $data);
	}

	private function extractFileNames(array $files): array
	{
		$names = [];
		foreach ($files as $file) {
			if (!isset($file['name'], $file['error'], $file['size'])) continue;

			$nameList = is_array($file['name']) ? $file['name'] : [$file['name']];
			$errList = is_array($file['error']) ? $file['error'] : [$file['error']];
			$sizeList = is_array($file['size']) ? $file['size'] : [$file['size']];

			foreach ($nameList as $i => $name) {
				$err = $errList[$i] ?? UPLOAD_ERR_NO_FILE;
				$size = $sizeList[$i] ?? 0;
				if ($err === UPLOAD_ERR_OK && $size > 0 && $name) {
					$names[] = (string)$name;
				}
			}
		}
		return $names;
	}

	private function toastOnUpload(array $uploaded, array $data): void
	{
		$saved = [];
		foreach ($uploaded as $field => $was) {
			if ($was && !empty($data[$field])) {
				$saved[] = $data[$field];
			}
		}

		if ($saved) {
			$this->toast(t('Archivo(s) guardado(s): ') . implode(', ', $saved));
		} elseif (!empty($_FILES)) {
			$this->toast(t('Sin archivos nuevos; se preservan los existentes.'));
		}
	}

	protected function toast($msg)
	{
		Session::setArray('toast', $msg);
	}

	/* =====================
	 * CONSULTAS COMUNES
	 * ===================== */
	private function existsById(string $idu): bool
	{
		return $idu && (bool)parent::first(
			"SELECT 1 FROM " . self::TABLE . " WHERE idu = ? AND usuarios_idu = ? LIMIT 1",
			[$idu, $this->userId()]
		);
	}

	private function getPreviousMedia(string $idu): ?object
	{
		return parent::first(
			"SELECT fotos, mp3, mp4 FROM " . self::TABLE . " WHERE idu = ? AND usuarios_idu = ? LIMIT 1",
			[$idu, $this->userId()]
		);
	}

	/* =====================
	 * PÚBLICO
	 * ===================== */
	public function salvar(array $input)
	{
		$data = $this->normalize($input);

		if (empty($data['partidas_idu'])) {
			$this->toast(t('Falta partidas_idu.'));
			return '';
		}

		foreach (array_keys(self::MEDIA_FIELDS) as $field) {
			$data["_uploaded_$field"] = false;
		}
		if (!empty($_FILES)) {
			$this->processUploads($data);
		}

		$exists = $this->existsById($data['elementos_idu']);

		if ($exists) {
			$prev = $this->getPreviousMedia($data['elementos_idu']);
			$this->mergePreviousMedia($data, $prev);
			$this->actualizarElemento($data);
			$this->toast(t('¡Elemento actualizado en la partida!'));
			return $data['elementos_idu'];
		}

		$this->insert($data);
		$this->toast(t('¡Elemento creado en la partida!'));
		return $data['elementos_idu'];
	}

	public function establecerFondo(array $data): void
	{
		$userId = $this->userId();
		$partidaId = (string)($data['partidas_idu'] ?? '');
		$elementoId = (string)($data['elementos_idu'] ?? '');

		if (!$partidaId || !$elementoId) return;

		parent::query(
			"UPDATE " . self::TABLE . " SET fondo = 0 WHERE usuarios_idu = ? AND partidas_idu = ?",
			[$userId, $partidaId]
		);

		parent::query(
			"UPDATE " . self::TABLE . " SET fondo = 1 WHERE usuarios_idu = ? AND idu = ? AND partidas_idu = ?",
			[$userId, $elementoId, $partidaId]
		);

		$this->toast(t('Fondo actualizado para la partida.'));
	}

	public function crearMensaje(array $data): void
	{
		$partidas_idu = (string)($data['partidas_idu'] ?? '');
		$texto = (string)($data['mensaje'] ?? $data['texto'] ?? '');

		if (!$partidas_idu || $texto === '') {
			$this->toast(t('Mensaje vacío o faltan datos.'));
			return;
		}

		parent::query(
			"INSERT INTO " . self::TABLE . " (usuarios_idu, partidas_idu, formato, texto, creado) VALUES (?, ?, ?, ?, ?)",
			[$this->userId(), $partidas_idu, self::FORMATO_MENSAJE, $texto, $this->now()]
		);
		$this->toast(t('Mensaje enviado.'));
	}

	public function eliminar($idu): void
	{
		$idu = is_array($idu)
			? ((string)($idu['elementos_idu'] ?? $idu['idu'] ?? ''))
			: (string)$idu;

		if (!$idu) return;

		parent::query(
			"DELETE FROM " . self::TABLE . " WHERE idu = ? AND formato = ? AND usuarios_idu = ?",
			[$idu, self::FORMATO_ELEMENTO, $this->userId()]
		);
		$this->toast(t('Elemento eliminado de la partida.'));
	}

	public function eliminarTodos(string $partidas_idu): void
	{
		if (!$partidas_idu) return;

		parent::query(
			"DELETE FROM " . self::TABLE . " WHERE usuarios_idu = ? AND partidas_idu = ? AND formato = ?",
			[$this->userId(), $partidas_idu, self::FORMATO_ELEMENTO]
		);
		$this->toast(t('Elementos de la partida eliminados.'));
	}

	public function guardarPosicion(string $elementos_idu, $x, $y): void
	{
		if (!$elementos_idu) return;

		parent::query(
			"UPDATE " . self::TABLE . " SET posicion_x = ?, posicion_y = ? WHERE idu = ? AND formato = ? AND usuarios_idu = ?",
			[$this->safeIntDef($x, 0), $this->safeIntDef($y, 0), $elementos_idu, self::FORMATO_ELEMENTO, $this->userId()]
		);
	}

	/* Alias llamado por el PanelController */
	public function actualizarPosicion(string $elementos_idu, $x, $y): void
	{
		$this->guardarPosicion($elementos_idu, $x, $y);
	}

	public function importarElementos(string $aventuras_idu, string $partidas_idu): void
	{
		if (!$aventuras_idu || !$partidas_idu) return;

		$base = (new Aventuras_elementos)->todos($aventuras_idu);
		if (!$base) return;

		$placeholders = [];
		$values = [];

		foreach ($base as $ele) {
			$placeholders[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
			$values = array_merge($values, [
				$this->userId(),
				$partidas_idu,
				$ele->idu,
				(int)($ele->peso ?? 0),
				self::FORMATO_ELEMENTO,
				$ele->nombre ?? 'Sin nombre',
				$ele->tipo ?? 'texto',
				$ele->notas ?? '',
				$ele->fotos ?? '',
				'', '', '',
				0,
				self::DEFAULT_POS,
				self::DEFAULT_POS,
				$this->now()
			]);
		}

		$sql = "INSERT INTO " . self::TABLE .
			" (usuarios_idu, partidas_idu, idu, peso, formato, nombre, tipo, texto, fotos, mp3, mp4, youtube, enviado, posicion_x, posicion_y, creado)" .
			" VALUES " . implode(', ', $placeholders);

		parent::query($sql, $values);
		$this->toast(t('Elementos importados desde la aventura.'));
	}

	public function mostrarElemento(string $elementos_idu): void
	{
		if (!$elementos_idu) return;

		$current = parent::first(
			"SELECT enviado FROM " . self::TABLE . " WHERE idu = ? AND usuarios_idu = ? LIMIT 1",
			[$elementos_idu, $this->userId()]
		);
		$currVal = $current ? (int)$current->enviado : 1;
		$nuevo = $currVal ? 0 : 1;

		parent::query(
			"UPDATE " . self::TABLE . " SET enviado = ? WHERE idu = ? AND usuarios_idu = ?",
			[$nuevo, $elementos_idu, $this->userId()]
		);

		$this->toast($nuevo ? t('Elemento mostrado a los jugadores.') : t('Elemento ocultado a los jugadores.'));
	}

	public function obtenerFondo(string $partidas_idu): object
	{
		if (!$partidas_idu) {
			return (object)['idu' => '', 'elementos_idu' => ''];
		}

		$fondo = parent::first(
			"SELECT *, idu AS elementos_idu FROM " . self::TABLE . " WHERE partidas_idu = ? AND usuarios_idu = ? AND fondo = 1",
			[$partidas_idu, $this->userId()]
		);

		return $fondo ?: (object)['idu' => '', 'elementos_idu' => ''];
	}

	public function todos(string $partidas_idu, string $filtro = ''): array
	{
		if (!$partidas_idu) return [];

		$sql = "SELECT *, idu AS elementos_idu FROM " . self::TABLE . " WHERE partidas_idu = ? AND usuarios_idu = ?";
		$params = [$partidas_idu, $this->userId()];

		if ($filtro === 'visibles') {
			$sql .= " AND enviado = 1";
		}
		return parent::arrayBy(parent::all($sql, $params), 'elementos_idu');
	}

	public function uno(string $elementos_idu, string $filtro = ''): ?object
	{
		if (!$elementos_idu) return null;

		$sql = "SELECT *, idu AS elementos_idu FROM " . self::TABLE . " WHERE idu = ? AND usuarios_idu = ?";
		$params = [$elementos_idu, $this->userId()];

		if ($filtro === 'visible') {
			$sql .= " AND enviado = 1";
		}

		return parent::first($sql, $params);
	}

	/* =====================
	 * INSERCIÓN / UPDATE
	 * ===================== */
	private function insert(array $data): void
	{
		$this->ensureUid($data);

		$sql = "INSERT INTO " . self::TABLE . " SET
			usuarios_idu = ?, partidas_idu = ?, idu = ?, peso = ?, formato = ?,
			nombre = ?, tipo = ?, texto = ?, fotos = ?, mp3 = ?, mp4 = ?, youtube = ?,
			enviado = ?, posicion_x = ?, posicion_y = ?, creado = ?";

		parent::query($sql, [
			$this->userId(),
			$data['partidas_idu'],
			$data['elementos_idu'],
			$data['peso'],
			self::FORMATO_ELEMENTO,
			$data['nombre'],
			$data['tipo'],
			$data['texto'],
			$data['fotos'],
			$data['mp3'],
			$data['mp4'],
			$data['youtube'],
			$data['enviado'],
			$this->safeIntDef($data['posicion_x'], self::DEFAULT_POS),
			$this->safeIntDef($data['posicion_y'], self::DEFAULT_POS),
			$this->now()
		]);
	}

	private function actualizarElemento(array $data): void
	{
		$sql = "UPDATE " . self::TABLE . " SET
			peso = ?, nombre = ?, tipo = ?, texto = ?, fotos = ?, mp3 = ?, mp4 = ?, youtube = ?, enviado = ?,
			posicion_x = ?, posicion_y = ?
			WHERE idu = ? AND usuarios_idu = ?";

		parent::query($sql, [
			$data['peso'],
			$data['nombre'],
			$data['tipo'],
			$data['texto'],
			$data['fotos'],
			$data['mp3'],
			$data['mp4'],
			$data['youtube'],
			$data['enviado'],
			$this->safeIntDef($data['posicion_x'], self::DEFAULT_POS),
			$this->safeIntDef($data['posicion_y'], self::DEFAULT_POS),
			$data['elementos_idu'],
			$this->userId()
		]);
	}
}
