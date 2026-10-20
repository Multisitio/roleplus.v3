<?php
/** Lectura de una copia resumida; la web no accede al estado privado del vigilante. */
class Vigilante
{
    public function detalle(string $referencia, string $usuario): ?array
    {
        $path = APP_PATH . 'temp/vigilante/details.json';
        if (!is_readable($path) || filesize($path) > 2097152) { return null; }
        try {
            $data = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
            return self::seleccionar($data, $referencia, $usuario);
        } catch (Throwable $ignored) {
            return null;
        }
    }

    public static function seleccionar(array $data, string $referencia, string $usuario): ?array
    {
        if (($data['version'] ?? null) !== 1 || $usuario === '' ||
            !is_string($data['recipient'] ?? null) || !hash_equals($data['recipient'], $usuario)) {
            return null;
        }
        if ($referencia !== 'prueba' && !preg_match('/^[a-f0-9]{64}$/D', $referencia)) { return null; }
        if (!is_int($data['observed_at'] ?? null) || !is_array($data['items'] ?? null) || count($data['items']) > 4096) { return null; }
        $labels = [
            'site_down' => 'Web no disponible', 'service_down' => 'Servicio no disponible',
            'php_fatal' => 'Error fatal de PHP', 'memory_exhausted' => 'Memoria agotada en PHP',
            'disk_space' => 'Espacio de disco en nivel crítico', 'inodes' => 'Inodos en nivel crítico',
        ];
        $items = [];
        foreach ($data['items'] as $item) {
            if (!isset($labels[$item['kind'] ?? '']) ||
                !preg_match('/^[a-z0-9]+(?:[.-][a-z0-9]+)*$/D', $item['site'] ?? '') ||
                !preg_match('/^[a-f0-9]{64}$/D', $item['reference'] ?? '')) { continue; }
            foreach (['first_seen', 'last_seen', 'count', 'code'] as $field) {
                if (!is_int($item[$field] ?? null)) { continue 2; }
            }
            if (!is_bool($item['observed'] ?? null) || !is_bool($item['accepted'] ?? null)) { continue; }
            $item['label'] = $labels[$item['kind']];
            $items[] = $item;
        }
        usort($items, fn($a, $b) => $b['last_seen'] <=> $a['last_seen']);
        $selected = null;
        foreach ($items as $item) {
            if ($item['reference'] === $referencia) { $selected = $item; break; }
        }
        if ($referencia !== 'prueba' && $selected === null) { return null; }
        return ['reference' => $referencia, 'selected' => $selected, 'items' => $items,
            'observed_at' => $data['observed_at'], 'last_test' => (int) ($data['last_test'] ?? 0)];
    }
}
