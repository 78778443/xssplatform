<?php
require __DIR__ . '/../lib.php';

test('spa deep links and assets are served', function () {
    $base = 'http://127.0.0.1:18081';
    foreach (['/', '/login', '/project/1', '/modules', '/admin/modules'] as $path) {
        $r = (string) shell_exec("curl -sS -i '{$base}{$path}'");
        assertTrue(str_contains($r, 'HTTP/1.1 200'), "spa 200 {$path}");
        assertTrue(str_contains($r, '<div id="root">'), "spa shell {$path}");
    }

    $index = (string) file_get_contents(__DIR__ . '/../../dist/index.html');
    preg_match('~src="(/[^"]+\.js)"~', $index, $match);
    assertTrue(!empty($match[1]), 'dist index references an asset');
    $r = (string) shell_exec("curl -sS -o /dev/null -w '%{http_code}' '{$base}{$match[1]}'");
    assertSame('200', trim($r), 'asset served');

    $r = (string) shell_exec("curl -sS -i '{$base}/index.php?do=login'");
    assertTrue(str_contains($r, 'Location: /login'), 'legacy still redirects');
});
