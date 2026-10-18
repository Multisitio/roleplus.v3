<?php
/** Prueba aislada: sin sesion, base de datos, claves ni archivos de usuarios. */
class Config
{
    public static function get($key)
    {
        if ($key !== 'vistas.miniaturas') {
            throw new RuntimeException('Configuracion no autorizada en la prueba.');
        }
        return ['xs' => 64, 'l' => 640];
    }
}
class KumbiaException extends RuntimeException {}
require $argv[1];

set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$dir = sys_get_temp_dir() . '/roleplus-thumbnail-' . bin2hex(random_bytes(8));
mkdir($dir, 0700);
$checks = 0;
function check($value, $message)
{
    global $checks;
    if (!$value) {
        throw new RuntimeException($message);
    }
    $checks++;
}
try {
    $svg = $dir . '/vector.svg';
    file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="60"><rect width="120" height="60" fill="red"/></svg>');
    $hash = hash_file('sha256', $svg);
    check(Thumbnail::make($svg, 'xs') === $svg, 'El SVG debe conservar su original vectorial.');
    check(hash_file('sha256', $svg) === $hash && !file_exists($dir . '/xs.vector.svg'), 'El SVG no debe modificarse ni rasterizarse.');
    $image = imagecreatetruecolor(120, 60);
    imagefill($image, 0, 0, imagecolorallocate($image, 220, 50, 10));
    foreach (['png' => 'imagepng', 'jpg' => 'imagejpeg', 'gif' => 'imagegif',
              'webp' => 'imagewebp', 'avif' => 'imageavif'] as $extension => $encoder) {
        if (!function_exists($encoder)) {
            throw new RuntimeException('El entorno de prueba no dispone de ' . $encoder);
        }
        $source = $dir . '/original.' . $extension;
        $encoder($image, $source);
        $originalHash = hash_file('sha256', $source);
        $thumb = Thumbnail::make($source, 'xs');
        $info = getimagesize($thumb);
        check($info[0] === 64 && $info[1] === 32, 'La miniatura debe conservar las proporciones: ' . $extension);
        check($info[2] === getimagesize($source)[2], 'La miniatura debe conservar su formato: ' . $extension);
        check(hash_file('sha256', $source) === $originalHash, 'La miniatura no debe modificar el original: ' . $extension);
        $thumbHash = hash_file('sha256', $thumb);
        check(Thumbnail::make($source, 'xs') === $thumb && hash_file('sha256', $thumb) === $thumbHash,
              'La miniatura existente debe reutilizarse: ' . $extension);
    }
    check(count(glob($dir . '/.thumb-*')) === 0, 'No deben quedar escrituras parciales.');
    echo json_encode(['passed' => true, 'checks' => $checks, 'formats' => ['svg','avif','png','jpg','gif','webp']]) . PHP_EOL;
} finally {
    restore_error_handler();
    foreach (glob($dir . '/*') as $file) {
        unlink($file);
    }
    rmdir($dir);
}
