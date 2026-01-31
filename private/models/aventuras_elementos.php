<?php
/**
 * Modelo: Elementos originales de una aventura.
 * Tabla: aventuras_elementos
 * UID: idu (acepta elementos_idu → mapea a idu)
 */
class Aventuras_elementos extends LiteRecord
{
	// === CONFIGURACIÓN ===
	private const TABLE = 'aventuras_elementos';
	private const MEDIA_FIELDS = [
		'fotos' => '/\.(gif|jpe?g|png|svgz?|webp)$/i',
		'mp3'	=> '/\.mp3$/i',
		'mp4'	=> '/\.mp4$/i',
	];

	// === UTILIDADES PRIVADAS ===
	private function userId(): string
	{
		return Session::get('idu');
	}

	private function uid(): string
	{
		return _str::uid();
	}

	private function safeInt($value, int $default = 0): int
	{
		return is_numeric($value) ? (int)$value : $default;
	}

	private function normalize(array $input): array
	{
		$defaults = [
			'idu'			=> '',
			'aventuras_idu'	=> '',
			'peso'			=> '0',
			'color'			=> 'yellow',
			'nombre'		=> 'Sin nombre',
			'fotos'			=> '',
			'notas'			=> '',
			'mapa'			=> 0,
			'ocultar'		=> 0,
		];

		$data = array_merge($defaults, array_intersect_key($input, $defaults));

		// Mapeo: elementos_idu → idu
		$data['idu'] = (string)($input['elementos_idu'] ?? $input['idu'] ?? '');

		// Normalización booleana
		$data['mapa']	= !empty($input['mapa']) ? 1 : 0;
		$data['ocultar']= !empty($input['ocultar']) ? 1 : 0;

		// Compat: "imagenes" del form → fotos
		if (!empty($input['imagenes']) && empty($data['fotos'])) {
			$data['fotos'] = (string)$input['imagenes'];
		}

		// Compat: textarea del form "texto" / "mensaje" → notas
		if (empty($data['notas'])) {
			if (!empty($input['texto'])) {
				$data['notas'] = (string)$input['texto'];
			} elseif (!empty($input['mensaje'])) {
				$data['notas'] = (string)$input['mensaje'];
			}
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
		$saved = [];

		$files = (new Archivos)->incluir($_FILES) ?? [];
		$files = is_array($files) ? $files : ($files ? [$files] : []);
		$files = array_merge($files, $this->extractFileNames($_FILES));

		foreach ($files as $filename) {
			if (!$filename = trim($filename)) continue;

			foreach (self::MEDIA_FIELDS as $field => $pattern) {
				if (!$uploaded[$field] && preg_match($pattern, $filename)) {
					$data[$field] = $filename;
					$uploaded[$field] = true;
					$saved[] = $filename;
					break 2;
				}
			}
		}

		foreach ($uploaded as $field => $was) {
			$data["_uploaded_$field"] = $was;
		}

		$this->toastOnUpload($saved);
	}

	private function extractFileNames(array $files): array
	{
		$names = [];
		foreach ($files as $file) {
			if (!isset($file['name'], $file['error'], $file['size'])) continue;

			$nameList = is_array($file['name']) ? $file['name'] : [$file['name']];
			$errList  = is_array($file['error']) ? $file['error'] : [$file['error']];
			$sizeList = is_array($file['size']) ? $file['size'] : [$file['size']];

			foreach ($nameList as $i => $name) {
				$err  = $errList[$i] ?? UPLOAD_ERR_NO_FILE;
				$size = $sizeList[$i] ?? 0;
				if ($err === UPLOAD_ERR_OK && $size > 0 && $name) {
					$names[] = $name;
				}
			}
		}
		return $names;
	}

	private function toastOnUpload(array $saved): void
	{
		if ($saved) {
			$this->toast(t('Archivo(s) guardado(s): ') . implode(', ', $saved));
		} elseif (!empty($_FILES)) {
			$this->toast(t('Sin archivos nuevos; se preservan los existentes.'));
		}
	}

	private function toast(string $msg): void
	{
		Session::setArray('toast', $msg);
	}

	/* ==== CONSULTAS AUXILIARES (nombres compuestos) ==== */
	private function existsRow(string $idu): bool
	{
		return $idu && (bool)self::first(
			"SELECT 1 FROM " . self::TABLE . " WHERE idu = ? LIMIT 1",
			[$idu]
		);
	}

	private function getPreviousMedia(string $idu): ?object
	{
		return self::first(
			"SELECT fotos, mp3, mp4 FROM " . self::TABLE . " WHERE idu = ? LIMIT 1",
			[$idu]
		);
	}

	// === PÚBLICO ===
	public function salvar(array $input)
	{
		$data = $this->normalize($input);

		foreach (array_keys(self::MEDIA_FIELDS) as $field) {
			$data["_uploaded_$field"] = false;
		}

		if (!empty($_FILES)) {
			$this->processUploads($data);
		}

		$exists = $this->existsRow($data['idu']);

		if ($exists) {
			$prev = $this->getPreviousMedia($data['idu']);
			$this->mergePreviousMedia($data, $prev);
			$this->updateRow($data);
			$this->toast(t('¡Elemento actualizado en la aventura!'));
			return $data['idu'];
		}

		$idu = $this->insertRow($data);
		$this->toast(t('¡Elemento creado en la aventura!'));
		return $idu;
	}

	public function eliminar($input): void
	{
		$aplicar_en = $input['aplicar_en'] ?? 'aventura';
		$idu = is_array($input)
			? ($input['elementos_idu'] ?? $input['idu'] ?? '')
			: (string)$input;

		if (in_array($aplicar_en, ['partida', 'ambas'])) {
			(new Partidas_elementos)->eliminar($idu);
		}

		if (in_array($aplicar_en, ['aventura', 'ambas'])) {
			self::query(
				"DELETE FROM " . self::TABLE . " WHERE (usuarios_idu = ? OR usuarios_idu IS NULL) AND idu = ?",
				[$this->userId(), $idu]
			);
			$this->toast(t('Elemento eliminado de la aventura.'));
		}
	}

	public function todos(string $aventuras_idu): array
	{
		return self::all(
			"SELECT * FROM " . self::TABLE . " WHERE aventuras_idu = ? ORDER BY peso",
			[$aventuras_idu]
		);
	}

	public function uno(string $idu = ''): object
	{
		$row = self::first(
			"SELECT * FROM " . self::TABLE . " WHERE idu = ?",
			[$idu]
		);

		return $row ?: parent::cols();
	}

	// === INSERCIÓN / ACTUALIZACIÓN (nombres compuestos) ===
	private function insertRow(array $data): string
	{
		$idu = $data['idu'] ?: $this->uid();

		self::query(
			"INSERT INTO " . self::TABLE . " SET
				usuarios_idu = ?, aventuras_idu = ?, peso = ?, color = ?,
				nombre = ?, idu = ?, fotos = ?, notas = ?, mapa = ?, ocultar = ?",
			[
				$this->userId(),
				$data['aventuras_idu'],
				$data['peso'],
				$data['color'],
				$data['nombre'],
				$idu,
				$data['fotos'],
				$data['notas'],
				$data['mapa'],
				$data['ocultar'],
			]
		);

		return $idu;
	}

	private function updateRow(array $data): void
	{
		self::query(
			"UPDATE " . self::TABLE . " SET
				usuarios_idu = ?, aventuras_idu = ?, peso = ?, color = ?,
				nombre = ?, fotos = ?, notas = ?, mapa = ?, ocultar = ?
				WHERE (usuarios_idu = ? OR usuarios_idu IS NULL) AND idu = ?",
			[
				$this->userId(),
				$data['aventuras_idu'],
				$data['peso'],
				$data['color'],
				$data['nombre'],
				$data['fotos'],
				$data['notas'],
				$data['mapa'],
				$data['ocultar'],
				$this->userId(),
				$data['idu'],
			]
		);
	}
}
