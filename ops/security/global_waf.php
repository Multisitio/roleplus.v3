<?php
/**
 * Server PHP prepend, deployed to /usr/share/php/global_waf.php.
 * Ordinary navigation and dynamic images must not share an IP request quota.
 */
ini_set('display_errors', 0);

function global_server_shield() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $query = $_SERVER['QUERY_STRING'] ?? '';

    $bots_to_block = '/(claudebot|chatgpt|gptbot|bytespider|semrushbot|ahrefsbot|mj12bot|dotbot|rogerbot|exabot|msnbot|bingbot)/i';
    if (preg_match($bots_to_block, $ua)) {
        header('HTTP/1.1 403 Forbidden');
        trigger_error("WAF: Blocked bot $ua from $ip", E_USER_NOTICE);
        die('Access denied (Security Policy: Bot Detected)');
    }

    if (strlen($query) > 400 || substr_count($query, '&') > 15) {
        header('HTTP/1.1 403 Forbidden');
        die('Invalid Request Pattern (Security Policy)');
    }
}

global_server_shield();
