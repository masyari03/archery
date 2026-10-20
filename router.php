<?php
/**
 * PHP Built-in Server Router for Target Archery SaaS
 * Handles clean URLs, static file serving, and XML sitemaps.
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 1. Direct sitemap.xml rewrite to dynamic generator
if ($uri === '/sitemap.xml') {
    require __DIR__ . '/sitemap.php';
    exit;
}

// 2. Allow direct access to real existing files & assets (css, js, images, etc.)
$file = __DIR__ . $uri;
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false; // Let PHP built-in server serve the static file directly
}

// 3. Root URL handling
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    exit;
}

// 4. Default fallback
return false;
