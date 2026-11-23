<?php
require __DIR__ . '/../lib.php';

test('root serves an html shell', function () {
    $raw = (string) shell_exec('curl -sS -i http://127.0.0.1:18080/');
    assertTrue(str_contains($raw, 'HTTP/1.1 200'), 'http 200');
    assertTrue(stripos($raw, 'content-type: text/html') !== false, 'html content type');
    assertTrue(str_contains($raw, 'XSS'), 'shell contains XSS');
});
