<?php
$url = "https://mariojpcsimon.blogspot.com/2026/06/alianza-hermetica-bosch-de-la-fera.html";
$html = file_get_contents($url);

$pos = strpos($html, 'NaveInvisible');
if ($pos !== false) {
    echo "=== CONTEXT FOR NAVEINVISIBLE ===\n";
    echo htmlspecialchars(substr($html, $pos - 400, 800)) . "\n";
} else {
    echo "NaveInvisible not found\n";
}
?>
