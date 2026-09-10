<?php
$file = '/var/www/roleplus.app/private/config/keys.php';
$content = file_get_contents($file);
if (strpos($content, "'smtp'") === false) {
    $content = str_replace(
        "];",
        "    'smtp' => [\n        'ia' => 'h1SCTwrbmPZRcgnAtiG1tPPFMokBrVBG',\n    ],\n];",
        $content
    );
    file_put_contents($file, $content);
    echo "Keys updated.\n";
} else {
    echo "SMTP already exists.\n";
}
