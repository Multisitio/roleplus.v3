<?php
/**
 * Canonical processing pipeline for user-supplied images.
 *
 * Static raster images become WebP, animated opaque GIFs become MP4, animated
 * GIFs with transparency become animated WebP, and SVG files are sanitised.
 * Files are written beside their legacy RolePlus thumbnail aliases so old URLs
 * and ImgController continue to work during the migration.
 */
class MediaProcessor
{
    public const MAX_BYTES = 16777216;
    public const MAX_PIXELS = 40000000;
    public const MAX_ANIMATION_PIXELS = 120000000;
    public const MAX_WIDTH = 1920;
    public const WEBP_QUALITY = 82;

    private const SIZES = [
        'xxs' => 48,
        'xs' => 64,
        's' => 96,
        'm' => 384,
        'l' => 640,
        'xl' => 1024,
        'xxl' => 1920,
    ];

    private const RASTER_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/avif',
        'image/bmp',
        'image/x-ms-bmp',
        'image/tiff',
        'image/heic',
        'image/heif',
    ];

    public static function processUpload(array $file, string $destinationDir, array $options = []): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('La subida no se completó (código ' . $error . ').');
        }

        $source = (string) ($file['tmp_name'] ?? '');
        if ($source === '' || !is_uploaded_file($source)) {
            throw new RuntimeException('El archivo no procede de una subida HTTP válida.');
        }

        $options['original_name'] = (string) ($file['name'] ?? 'imagen');
        $options['declared_size'] = (int) ($file['size'] ?? 0);
        return self::processPath($source, $destinationDir, $options);
    }

    /** Used by the historical migration after it has selected an exact file. */
    public static function processExisting(string $source, string $destinationDir, array $options = []): array
    {
        if (!is_file($source)) {
            throw new RuntimeException('No existe el archivo de origen.');
        }
        $options['original_name'] = $options['original_name'] ?? basename($source);
        return self::processPath($source, $destinationDir, $options);
    }

    private static function processPath(string $source, string $destinationDir, array $options): array
    {
        $size = (int) (@filesize($source) ?: ($options['declared_size'] ?? 0));
        $maxBytes = (int) ($options['max_bytes'] ?? self::MAX_BYTES);
        if ($size < 1 || $size > $maxBytes) {
            throw new RuntimeException('La imagen está vacía o supera el límite de ' . round($maxBytes / 1048576) . ' MB.');
        }

        $mime = self::mime($source);
        if ($mime !== 'image/svg+xml' && !in_array($mime, self::RASTER_MIMES, true)) {
            throw new RuntimeException('Formato de imagen no admitido: ' . $mime);
        }

        $dir = self::absoluteDirectory($destinationDir);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear la carpeta de destino.');
        }

        $stem = self::safeStem((string) ($options['basename'] ?? pathinfo((string) $options['original_name'], PATHINFO_FILENAME)));
        if ($stem === '') {
            $stem = bin2hex(random_bytes(10));
        }

        if ($mime === 'image/svg+xml') {
            return self::processSvg($source, $dir, $stem);
        }
        if (!class_exists('Imagick')) {
            throw new RuntimeException('ImageMagick es necesario para procesar imágenes.');
        }

        $image = new Imagick();
        try {
            $image->readImage($source);
            $frames = $image->getNumberImages();
            self::validateDimensions($image, $frames);

            if ($frames > 1) {
                $mustRemainImage = $mime !== 'image/gif'
                    || ($options['animated_gif'] ?? 'mp4') === 'webp'
                    || self::gifHasTransparency($source)
                    || self::hasTransparency($image);
                if ($mustRemainImage) {
                    return self::writeAnimatedWebp($image, $dir, $stem, $options);
                }
                return self::writeGifAsMp4($source, $image, $dir, $stem, $options);
            }

            $image->setIteratorIndex(0);
            return self::writeStaticWebp($image, $dir, $stem, $options);
        } finally {
            $image->clear();
            $image->destroy();
        }
    }

    private static function processSvg(string $source, string $dir, string $stem): array
    {
        $xml = (string) file_get_contents($source);
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw new RuntimeException('El SVG contiene declaraciones no permitidas.');
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded || !$dom->documentElement || strtolower($dom->documentElement->localName) !== 'svg') {
            throw new RuntimeException('El SVG no es válido.');
        }

        $xpath = new DOMXPath($dom);
        foreach (['script', 'foreignObject', 'iframe', 'object', 'embed', 'style', 'audio', 'video'] as $tag) {
            $nodes = $xpath->query('//*[local-name()="' . $tag . '"]');
            if (!$nodes) {
                continue;
            }
            foreach (iterator_to_array($nodes) as $node) {
                $node->parentNode->removeChild($node);
            }
        }

        foreach (iterator_to_array($xpath->query('//*') ?: []) as $element) {
            foreach (iterator_to_array($element->attributes ?: []) as $attribute) {
                $name = strtolower($attribute->nodeName);
                $value = trim($attribute->nodeValue);
                $lowerValue = strtolower($value);
                $isEvent = strncmp($name, 'on', 2) === 0;
                $isStyle = $name === 'style';
                $isLink = $name === 'href' || $name === 'xlink:href';
                $unsafeValue = strpos($lowerValue, 'javascript:') !== false
                    || strpos($lowerValue, 'data:text/html') !== false
                    || strpos($lowerValue, 'expression(') !== false
                    || (strpos($lowerValue, 'url(') !== false && strpos($lowerValue, 'url(#') === false);
                if ($isEvent || $isStyle || $unsafeValue || ($isLink && $value !== '' && $value[0] !== '#')) {
                    $element->removeAttributeNode($attribute);
                }
            }
        }

        $target = $dir . DIRECTORY_SEPARATOR . $stem . '.svg';
        self::atomicWrite($target, (string) $dom->saveXML($dom->documentElement));
        return [
            'name' => basename($target),
            'format' => 'svg',
            'mime' => 'image/svg+xml',
            'width' => null,
            'height' => null,
            'animated' => false,
            'poster' => null,
            'variants' => [],
        ];
    }

    private static function writeStaticWebp(Imagick $image, string $dir, string $stem, array $options): array
    {
        if (method_exists($image, 'autoOrientImage')) {
            $image->autoOrientImage();
        }
        $image->stripImage();
        $maxWidth = self::boundedMaxWidth($options);
        if ($image->getImageWidth() > $maxWidth) {
            self::resizeToWidth($image, $maxWidth);
        }
        self::prepareWebpFrame($image, self::quality($options));

        $target = $dir . DIRECTORY_SEPARATOR . $stem . '.webp';
        self::writeImagickAtomically($image, $target, false);
        $width = $image->getImageWidth();
        $height = $image->getImageHeight();
        $variants = self::writeStaticVariants($target, $dir, basename($target), $width, $options);

        return [
            'name' => basename($target),
            'format' => 'webp',
            'mime' => 'image/webp',
            'width' => $width,
            'height' => $height,
            'animated' => false,
            'poster' => null,
            'variants' => $variants,
        ];
    }

    private static function writeAnimatedWebp(Imagick $image, string $dir, string $stem, array $options): array
    {
        $sequence = $image->coalesceImages();
        $maxWidth = self::boundedMaxWidth($options);
        foreach ($sequence as $frame) {
            if ($frame->getImageWidth() > $maxWidth) {
                self::resizeToWidth($frame, $maxWidth);
            }
            $frame->setImagePage(0, 0, 0, 0);
            self::prepareWebpFrame($frame, self::quality($options));
        }

        $target = $dir . DIRECTORY_SEPARATOR . $stem . '.webp';
        self::writeImagickAtomically($sequence, $target, true);
        $sequence->setIteratorIndex(0);
        $width = $sequence->getImageWidth();
        $height = $sequence->getImageHeight();
        $variants = self::writeAnimatedVariants($target, $dir, basename($target), $width, $options);
        $sequence->clear();
        $sequence->destroy();

        return [
            'name' => basename($target),
            'format' => 'webp',
            'mime' => 'image/webp',
            'width' => $width,
            'height' => $height,
            'animated' => true,
            'poster' => null,
            'variants' => $variants,
        ];
    }

    private static function writeGifAsMp4(string $source, Imagick $image, string $dir, string $stem, array $options): array
    {
        if (!function_exists('proc_open')) {
            throw new RuntimeException('FFmpeg no puede ejecutarse en este servidor.');
        }
        $target = $dir . DIRECTORY_SEPARATOR . $stem . '.mp4';
        $temporary = self::temporaryPath($target);
        $maxWidth = self::boundedMaxWidth($options);
        $filter = "scale='min({$maxWidth},iw)':-2:flags=lanczos,format=yuv420p";
        $command = [
            'ffmpeg', '-y', '-hide_banner', '-loglevel', 'error', '-i', $source,
            '-an', '-vf', $filter, '-c:v', 'libx264', '-preset', 'medium',
            '-crf', '25', '-movflags', '+faststart', '-f', 'mp4', $temporary,
        ];
        [$exitCode, $stderr] = self::run($command);
        if ($exitCode !== 0 || !is_file($temporary) || filesize($temporary) < 1) {
            @unlink($temporary);
            throw new RuntimeException('FFmpeg no pudo convertir el GIF: ' . trim($stderr));
        }
        self::replace($temporary, $target);

        $image->setIteratorIndex(0);
        $posterStem = $stem . '.poster';
        $posterResult = self::writeStaticWebp(clone $image, $dir, $posterStem, [
            'max_width' => $maxWidth,
            'quality' => self::quality($options),
            'sizes' => [],
        ]);

        return [
            'name' => basename($target),
            'format' => 'mp4',
            'mime' => 'video/mp4',
            'width' => $posterResult['width'],
            'height' => $posterResult['height'],
            'animated' => true,
            'poster' => $posterResult['name'],
            'variants' => [],
        ];
    }

    private static function writeStaticVariants(string $source, string $dir, string $name, int $sourceWidth, array $options): array
    {
        $variants = [];
        foreach (self::requestedSizes($options) as $prefix => $width) {
            $target = $dir . DIRECTORY_SEPARATOR . $prefix . '.' . $name;
            if ($width >= $sourceWidth) {
                self::linkOrCopy($source, $target);
            } else {
                $variant = new Imagick($source);
                self::resizeToWidth($variant, $width);
                self::prepareWebpFrame($variant, self::quality($options));
                self::writeImagickAtomically($variant, $target, false);
                $variant->clear();
                $variant->destroy();
            }
            $variants[$prefix] = basename($target);
        }
        return $variants;
    }

    private static function writeAnimatedVariants(string $source, string $dir, string $name, int $sourceWidth, array $options): array
    {
        $variants = [];
        foreach (self::requestedSizes($options) as $prefix => $width) {
            $target = $dir . DIRECTORY_SEPARATOR . $prefix . '.' . $name;
            if ($width >= $sourceWidth) {
                self::linkOrCopy($source, $target);
            } else {
                $variant = new Imagick($source);
                $sequence = $variant->coalesceImages();
                foreach ($sequence as $frame) {
                    self::resizeToWidth($frame, $width);
                    $frame->setImagePage(0, 0, 0, 0);
                    self::prepareWebpFrame($frame, self::quality($options));
                }
                self::writeImagickAtomically($sequence, $target, true);
                $sequence->clear();
                $sequence->destroy();
                $variant->clear();
                $variant->destroy();
            }
            $variants[$prefix] = basename($target);
        }
        return $variants;
    }

    private static function prepareWebpFrame(Imagick $image, int $quality): void
    {
        $image->setImageFormat('webp');
        $image->setImageCompressionQuality($quality);
        $image->setOption('webp:method', '6');
        $image->stripImage();
    }

    private static function resizeToWidth(Imagick $image, int $width): void
    {
        $sourceWidth = max(1, $image->getImageWidth());
        $sourceHeight = max(1, $image->getImageHeight());
        $height = max(1, (int) round($sourceHeight * ($width / $sourceWidth)));
        $image->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1, false);
    }

    private static function hasTransparency(Imagick $image): bool
    {
        foreach ($image as $frame) {
            if (!$frame->getImageAlphaChannel()) {
                continue;
            }
            $extrema = $frame->getImageChannelExtrema(Imagick::CHANNEL_ALPHA);
            $range = Imagick::getQuantumRange();
            $maximum = (int) ($range['quantumRangeLong'] ?? max($extrema));
            if ((int) ($extrema['min'] ?? $maximum) < $maximum) {
                return true;
            }
        }
        return false;
    }

    private static function gifHasTransparency(string $source): bool
    {
        $data = (string) file_get_contents($source);
        $offset = 0;
        while (($offset = strpos($data, "\x21\xF9\x04", $offset)) !== false) {
            if (isset($data[$offset + 3]) && (ord($data[$offset + 3]) & 1) === 1) {
                return true;
            }
            $offset += 3;
        }
        return false;
    }

    private static function validateDimensions(Imagick $image, int $frames): void
    {
        $image->setIteratorIndex(0);
        $width = $image->getImageWidth();
        $height = $image->getImageHeight();
        $pixels = $width * $height;
        if ($width < 1 || $height < 1 || $pixels > self::MAX_PIXELS || $frames > 1000 || $pixels * $frames > self::MAX_ANIMATION_PIXELS) {
            throw new RuntimeException('Las dimensiones o el número de fotogramas exceden los límites permitidos.');
        }
    }

    private static function requestedSizes(array $options): array
    {
        if (array_key_exists('sizes', $options)) {
            $requested = (array) $options['sizes'];
            $sizes = [];
            foreach ($requested as $key => $value) {
                if (is_string($key) && is_numeric($value)) {
                    $sizes[$key] = (int) $value;
                } elseif (is_string($value) && isset(self::SIZES[$value])) {
                    $sizes[$value] = self::SIZES[$value];
                }
            }
            return $sizes;
        }
        return self::SIZES;
    }

    private static function mime(string $source): string
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        return strtolower((string) $finfo->file($source));
    }

    private static function safeStem(string $stem): string
    {
        if (function_exists('normalizer_normalize')) {
            $stem = normalizer_normalize($stem, Normalizer::FORM_KD);
        }
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $stem);
        $stem = strtolower((string) ($ascii !== false ? $ascii : $stem));
        return trim((string) preg_replace('/[^a-z0-9_-]+/', '-', $stem), '-_');
    }

    private static function absoluteDirectory(string $directory): string
    {
        if (preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $directory)) {
            return rtrim($directory, '/\\');
        }
        $root = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
        if ($root === '' && defined('PUB_PATH')) {
            $root = PUB_PATH;
        }
        if ($root === '') {
            $root = getcwd();
        }
        return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $directory), '/\\');
    }

    private static function quality(array $options): int
    {
        return max(40, min(95, (int) ($options['quality'] ?? self::WEBP_QUALITY)));
    }

    private static function boundedMaxWidth(array $options): int
    {
        return max(320, min(self::MAX_WIDTH, (int) ($options['max_width'] ?? self::MAX_WIDTH)));
    }

    private static function writeImagickAtomically(Imagick $image, string $target, bool $sequence): void
    {
        $temporary = self::temporaryPath($target);
        $ok = $sequence ? $image->writeImages($temporary, true) : $image->writeImage($temporary);
        if (!$ok || !is_file($temporary) || filesize($temporary) < 1) {
            @unlink($temporary);
            throw new RuntimeException('No se pudo escribir la imagen procesada.');
        }
        self::replace($temporary, $target);
    }

    private static function atomicWrite(string $target, string $contents): void
    {
        $temporary = self::temporaryPath($target);
        if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo escribir el archivo procesado.');
        }
        self::replace($temporary, $target);
    }

    private static function temporaryPath(string $target): string
    {
        return $target . '.part-' . bin2hex(random_bytes(6));
    }

    private static function replace(string $temporary, string $target): void
    {
        if (is_file($target) && !unlink($target)) {
            @unlink($temporary);
            throw new RuntimeException('No se pudo reemplazar el archivo existente.');
        }
        if (!rename($temporary, $target)) {
            @unlink($temporary);
            throw new RuntimeException('No se pudo completar la escritura atómica.');
        }
        @chmod($target, 0664);
    }

    private static function linkOrCopy(string $source, string $target): void
    {
        if (is_file($target) && !unlink($target)) {
            throw new RuntimeException('No se pudo reemplazar una variante existente.');
        }
        if (!@link($source, $target) && !copy($source, $target)) {
            throw new RuntimeException('No se pudo crear una variante de imagen.');
        }
        @chmod($target, 0664);
    }

    private static function run(array $command): array
    {
        $pipes = [];
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        if (!is_resource($process)) {
            return [1, 'No se pudo iniciar el proceso.'];
        }
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        return [proc_close($process), $stderr];
    }
}
