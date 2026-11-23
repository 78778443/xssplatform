<?php
/**
 * Router for PHP's built-in server (development and integration tests).
 * Static files are served as-is; PHP entrypoints are executed; deep links
 * fall back to index.php so the SPA shell is returned.
 */
$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? $path : '/';
if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
    http_response_code(400);
    echo 'Bad Request';
    return;
}

// Same deny-list as the production examples: tooling, tests and config are not web content.
foreach (['/tests/', '/scripts/', '/deploy/', '/frontend/', '/.git/', '/source/', '/docs/'] as $prefix) {
    if (str_starts_with($path, $prefix)) {
        http_response_code(404);
        echo 'Not Found';
        return;
    }
}
if (preg_match('~^/(?:config\.php.*|xssplatform\.sql|init\.php|composer\.(?:json|lock)|package(?:-lock)?\.json|README\.md)$~i', $path)
    || preg_match('~^/\.~', $path)
    || preg_match('~^/install/(?:upgrade\.php|install\.lock)$~i', $path)) {
    http_response_code(404);
    echo 'Not Found';
    return;
}

if ($path !== '/' && is_file($root . $path)) {
    return false;
}
if (in_array($path, ['/api.php', '/index.php', '/authtest.php'], true)) {
    require $root . $path;
    return;
}
require $root . '/index.php';
