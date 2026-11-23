<?php
/**
 * Router for PHP's built-in server (development and integration tests).
 * Static files are served as-is; PHP entrypoints are executed; deep links
 * fall back to index.php so the SPA shell is returned.
 */
$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? $path : '/';

if ($path !== '/' && is_file($root . $path)) {
    return false;
}
if (in_array($path, ['/api.php', '/index.php', '/authtest.php'], true)) {
    require $root . $path;
    return;
}
require $root . '/index.php';
