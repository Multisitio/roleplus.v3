<?php
require $argv[1] ?? __DIR__ . '/../../private/libs/media_processor.php';
$source = $argv[2];
$output = $argv[3];
$start = microtime(true);
$result = MediaProcessor::processExisting($source, $output, [
    'basename' => 'profile-test', 'animated_gif' => 'original',
]);
if ($result['format'] !== 'gif' || count($result['variants']) !== 7) {
    throw new RuntimeException('GIF profile compatibility aliases missing.');
}
$hash = hash_file('sha256', $source);
foreach (array_merge([$result['name']], array_values($result['variants'])) as $name) {
    if (hash_file('sha256', $output . '/' . $name) !== $hash) {
        throw new RuntimeException('GIF animation bytes changed: ' . $name);
    }
}
try {
    MediaProcessor::processExisting($source, $output, ['max_bytes' => 1, 'animated_gif' => 'original']);
    throw new LogicException('The upload size limit was bypassed.');
} catch (RuntimeException $expected) {
}
echo json_encode(['ok' => true, 'seconds' => microtime(true) - $start, 'bytes' => filesize($source), 'result' => $result], JSON_UNESCAPED_SLASHES), "\n";
