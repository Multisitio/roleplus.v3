<?php
if ($argc !== 3) {
    fwrite(STDERR, "Uso: php test-media-processor.php /ruta/media_processor.php /ruta/fixtures\n");
    exit(2);
}

require $argv[1];
$fixtures = rtrim($argv[2], '/');
$output = $fixtures . '/output';
if (!is_dir($output)) {
    mkdir($output, 0775, true);
}

function check($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$static = MediaProcessor::processExisting($fixtures . '/large.jpg', $output, ['basename' => 'static']);
check($static['format'] === 'webp', 'La imagen estática no terminó en WebP.');
check($static['width'] === 1920, 'No se aplicó el límite de 1920 px.');
check(isset($static['variants']['l'], $static['variants']['xxl']), 'Faltan variantes estáticas.');

$svgSource = $fixtures . '/hostile.svg';
file_put_contents($svgSource, '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(2)</script><a href="https://evil.invalid"><rect width="10" height="10" style="fill:red"/></a></svg>');
$svg = MediaProcessor::processExisting($svgSource, $output, ['basename' => 'safe-vector']);
$svgContents = file_get_contents($output . '/' . $svg['name']);
check(stripos($svgContents, '<script') === false, 'El saneado conservó un script.');
check(stripos($svgContents, 'onload') === false, 'El saneado conservó un evento.');
check(stripos($svgContents, 'evil.invalid') === false, 'El saneado conservó un enlace externo.');
check(stripos($svgContents, 'style=') === false, 'El saneado conservó estilos activos.');

$opaque = MediaProcessor::processExisting($fixtures . '/opaque.gif', $output, ['basename' => 'opaque']);
check($opaque['format'] === 'mp4' && $opaque['animated'], 'El GIF opaco no terminó en MP4 animado.');
check(is_file($output . '/' . $opaque['poster']), 'No se creó el poster del MP4.');

$transparent = MediaProcessor::processExisting($fixtures . '/transparent.gif', $output, ['basename' => 'transparent']);
check($transparent['format'] === 'webp' && $transparent['animated'], 'El GIF transparente no terminó en WebP animado.');
$animated = new Imagick($output . '/' . $transparent['name']);
check($animated->getNumberImages() > 1, 'El WebP transparente perdió la animación.');
$animated->clear();
$animated->destroy();
$animatedVariant = new Imagick($output . '/' . $transparent['variants']['l']);
check($animatedVariant->getNumberImages() > 1, 'La variante WebP perdió la animación.');
$animatedVariant->clear();
$animatedVariant->destroy();

echo json_encode([
    'ok' => true,
    'static' => $static,
    'svg' => $svg,
    'opaque_gif' => $opaque,
    'transparent_gif' => $transparent,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
