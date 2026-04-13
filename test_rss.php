<?php
$url = "https://www.youtube.com/feeds/videos.xml?channel_id=UCyRjPG0qW9Tm5ftFwLVq-CA";
$referer = 'https://www.youtube.com';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

$body = curl_exec($ch);
$info = curl_getinfo($ch);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP_CODE: " . $info['http_code'] . "\n";
echo "ERROR: " . $error . "\n";
echo "BODY_LEN: " . strlen($body) . "\n";

// Con X-forwarded-for
$ip = rand(0, 255) . "." . rand(0, 255) . "." . rand(0, 255) . "." . rand(0, 255);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Forwarded-For: $ip"]);
curl_setopt($ch, CURLOPT_REFERER, 'https://www.youtube.com');

$body2 = curl_exec($ch);
$info2 = curl_getinfo($ch);
$error2 = curl_error($ch);
curl_close($ch);

echo "\nCON X-FORWARDED:\n";
echo "HTTP_CODE: " . $info2['http_code'] . "\n";
echo "ERROR: " . $error2 . "\n";
echo "BODY_LEN: " . strlen($body2) . "\n";
