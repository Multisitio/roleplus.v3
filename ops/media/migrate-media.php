<?php
/**
 * Staged migration of historical RolePlus media.
 *
 * Dry-run is the default and never writes. --apply creates canonical files and
 * derivatives beside the originals, but deliberately does not delete originals
 * or modify the database. Every successful mapping is appended to JSONL so the
 * reference update can be reviewed and committed separately.
 *
 * Example:
 * php migrate-media.php --root=/var/www/.../web/img/usuarios \
 *   --processor=/var/www/.../private/libs/media_processor.php --limit=100
 * php migrate-media.php ... --apply --manifest=/root/roleplus-media.jsonl
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este programa solo puede ejecutarse desde CLI.\n");
    exit(2);
}

$options = getopt('', ['root:', 'processor:', 'apply', 'limit::', 'manifest::']);
$root = isset($options['root']) ? realpath($options['root']) : false;
$processor = isset($options['processor']) ? realpath($options['processor']) : false;
$apply = array_key_exists('apply', $options);
$limit = max(1, (int) ($options['limit'] ?? ($apply ? 100 : PHP_INT_MAX)));
$manifest = (string) ($options['manifest'] ?? (__DIR__ . '/media-migration.jsonl'));

if (!$root || !$processor || !is_dir($root) || !is_file($processor)) {
    fwrite(STDERR, "Son obligatorias rutas válidas para --root y --processor.\n");
    exit(2);
}
require $processor;

// Un WebP histórico ya es canónico. Reprocesarlo escribiría sobre el propio
// original, en contra de la garantía de esta migración de conservar fuentes.
$extensions = ['jpg', 'jpeg', 'png', 'gif', 'avif', 'bmp', 'tif', 'tiff', 'heic', 'heif'];
$skipName = '/^(?:xxs|xs|s|m|l|xl|xxl)\.|\.poster\.webp$/i';
$known = [];
if ($apply && is_file($manifest)) {
    $handle = fopen($manifest, 'rb');
    while (($line = fgets($handle)) !== false) {
        $entry = json_decode($line, true);
        if (!empty($entry['source'])) {
            $known[$entry['source']] = true;
        }
        if (!empty($entry['target'])) {
            $known[$entry['target']] = true;
        }
    }
    fclose($handle);
}

$summary = ['mode' => $apply ? 'apply' : 'dry-run', 'candidates' => 0, 'processed' => 0, 'skipped' => 0, 'failed' => 0, 'bytes' => 0];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $name = $file->getFilename();
    $extension = strtolower($file->getExtension());
    if (!in_array($extension, $extensions, true) || preg_match($skipName, $name)) {
        continue;
    }
    $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
    if (isset($known[$relative])) {
        ++$summary['skipped'];
        continue;
    }
    ++$summary['candidates'];
    $sourceBytes = $file->getSize();
    $summary['bytes'] += $sourceBytes;
    if (!$apply) {
        if ($summary['candidates'] >= $limit) {
            break;
        }
        continue;
    }

    try {
        $stem = pathinfo($name, PATHINFO_FILENAME);
        foreach ([$stem . '.webp', $stem . '.mp4'] as $possibleTarget) {
            $possiblePath = $file->getPath() . DIRECTORY_SEPARATOR . $possibleTarget;
            if ($possiblePath !== $file->getPathname() && is_file($possiblePath)) {
                throw new RuntimeException('Ya existe el destino ' . $possibleTarget . '; se omite para evitar sobrescribirlo.');
            }
        }
        $result = MediaProcessor::processExisting($file->getPathname(), $file->getPath(), [
            'basename' => $stem,
        ]);
        $entry = [
            'source' => $relative,
            'target' => ltrim(str_replace('\\', '/', substr($file->getPath() . DIRECTORY_SEPARATOR . $result['name'], strlen($root))), '/'),
            'source_bytes' => $sourceBytes,
            'target_bytes' => filesize($file->getPath() . DIRECTORY_SEPARATOR . $result['name']),
            'result' => $result,
            'created_at' => gmdate('c'),
        ];
        file_put_contents($manifest, json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
        $known[$entry['source']] = true;
        $known[$entry['target']] = true;
        ++$summary['processed'];
    } catch (Throwable $e) {
        ++$summary['failed'];
        if ($apply) {
            $failedEntry = [
                'source' => $relative,
                'status' => 'failed',
                'error' => $e->getMessage(),
                'created_at' => gmdate('c'),
            ];
            file_put_contents($manifest, json_encode($failedEntry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
            $known[$relative] = true;
        }
        fwrite(STDERR, $relative . ': ' . $e->getMessage() . PHP_EOL);
    }
    if ($summary['processed'] + $summary['failed'] >= $limit) {
        break;
    }
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
if ($summary['failed'] > 0) {
    exit(1);
}
