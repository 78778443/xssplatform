<?php
require __DIR__ . '/../lib.php';

test('meta endpoint returns json with install state', function () {
    $base = 'http://127.0.0.1:18081';
    $raw = (string) shell_exec("curl -sS -i '{$base}/api.php?action=meta'");
    assertTrue(str_contains($raw, 'HTTP/1.1 200'), 'http 200');
    assertTrue(stripos($raw, 'content-type: application/json') !== false, 'json content type');
    assertTrue(str_contains($raw, 'X-Content-Type-Options: nosniff'), 'nosniff');
    $body = substr($raw, strpos($raw, "\r\n\r\n") + 4);
    $json = json_decode($body, true);
    assertSame(true, $json['ok']);
    assertSame(true, $json['data']['installed']);
    assertSame('XSS平台', $json['data']['config']['sitename']);
});
