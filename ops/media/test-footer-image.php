<?php
require $argv[1];
$dir = sys_get_temp_dir() . '/roleplus-footer-test-' . bin2hex(random_bytes(5));
mkdir($dir);
$source = new Imagick();
$source->newImage(2400, 120, new ImagickPixel('transparent'), 'png');
$draw = new ImagickDraw();
$draw->setFillColor(new ImagickPixel('rgba(255,0,0,0.5)'));
$draw->rectangle(100, 10, 300, 110);
$source->drawImage($draw);
$source->writeImage($dir . '/source.png');
foreach ([false, true] as $preserve) {
    $result = MediaProcessor::processExisting($dir . '/source.png', $dir, [
        'basename' => $preserve ? 'footer' : 'normal', 'preserve_dimensions' => $preserve,
    ]);
    $image = new Imagick($dir . '/' . $result['name']);
    $width = $preserve ? 2400 : 1920;
    if ($image->getImageWidth() !== $width || $image->getImageHeight() !== ($preserve ? 120 : 96)) {
        throw new RuntimeException('Incorrect dimensions');
    }
    if ($image->getImagePixelColor(0, 0)->getColorValue(Imagick::COLOR_ALPHA) > 0.01) {
        throw new RuntimeException('Transparent pixels became opaque');
    }
    $alpha = $image->getImagePixelColor(150, 50)->getColorValue(Imagick::COLOR_ALPHA);
    if ($alpha < 0.45 || $alpha > 0.55) throw new RuntimeException('Partial transparency lost');
    $thumb = new Imagick($dir . '/' . $result['variants']['l']);
    if ($thumb->getImageWidth() !== 640) throw new RuntimeException('Legacy thumbnail missing');
    echo ($preserve ? 'Footer original dimensions' : 'Default resize') . ": PASS; alpha 0 and 0.5 preserved; thumbnail PASS\n";
}
