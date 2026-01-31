<?php
$input = "<h6>Fase 1</h6>"; // Fragmento simple
$input2 = '<article id="sec"><section><div><h4>Title</h4></div></section></article>'; // Fragmento complejo

function test($input, $useFlags) {
    echo "Testing input: " . htmlspecialchars(substr($input, 0, 50)) . "...\n";
    $dom = new DOMDocument();
    // Hack UTF-8
    $content = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $input;
    
    $flags = $useFlags ? (LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD) : 0;
    
    // Suppress warnings
    libxml_use_internal_errors(true);
    $dom->loadHTML($content, $flags);
    libxml_clear_errors();

    $body = $dom->getElementsByTagName('body')->item(0);
    
    if ($body) {
        echo "BODY FOUND. Children: " . $body->childNodes->length . "\n";
        $out = '';
        foreach ($body->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }
        echo "OUTPUT: " . htmlspecialchars($out) . "\n";
    } else {
        echo "BODY NOT FOUND (This causes the data loss!)\n";
        // Attempt to just save HTML
        echo "FULL SAVE: " . htmlspecialchars($dom->saveHTML()) . "\n";
    }
    echo "---------------------------------------------------\n";
}

echo "--- CON FLAGS (Lo que falla) ---\n";
test($input, true);
test($input2, true);

echo "\n--- SIN FLAGS (Lo que lo arregla) ---\n";
test($input, false);
test($input2, false);
