<?php
require __DIR__ . '/../lib.php';

test('receiver endpoints keep legacy behaviour', function () {
    $base = 'http://127.0.0.1:18081';
    $jar = '/tmp/xssp-rec.cookies';
    @unlink($jar);
    $r = (string) shell_exec("curl -sS -c {$jar} -X POST '{$base}/api.php?action=login' -H 'Content-Type: application/json' -d '{\"user\":\"admin\",\"pwd\":\"pw123456\"}'");
    $csrf = json_decode($r, true)['data']['csrf'];
    $r = (string) shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=project.create' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d '{\"title\":\"rec\",\"description\":\"\"}'");
    $p = json_decode($r, true)['data'];
    $urlKey = $p['urlKey'];
    $payload = '{"id":' . $p['id'] . ',"ty":"edit","modules":[1],"code":"alert(1)"}';
    $r = (string) shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=project.setcode.save' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d " . escapeshellarg($payload));
    assertTrue(str_contains($r, '"ok":true'), 'setcode');

    $r = (string) shell_exec("curl -sS -i '{$base}/index.php?do=code&urlKey={$urlKey}'");
    assertTrue(str_contains($r, 'Content-type: application/x-javascript'), 'js content type');
    assertTrue(str_contains($r, 'alert(1)'), 'custom code');
    assertTrue(str_contains($r, 'Cache-Control: nocache'), 'cache header');

    $r = (string) shell_exec("curl -sS -i '{$base}/index.php?do=api&id={$urlKey}&cookie=abc&location=http%3A%2F%2Fexample.com&toplocation=http%3A%2F%2Fexample.com' -H 'Referer: http://example.com/page'");
    assertTrue(str_contains($r, '302') || str_contains($r, 'Location: http://example.com/page'), 'redirect referer');
    $body = trim(substr($r, strpos($r, "\r\n\r\n") + 4));
    assertSame('', $body, 'no body');

    $r = (string) shell_exec("curl -sS '{$base}/index.php?do=do&auth={$p['authCode']}'");
    $d = json_decode($r, true);
    assertSame('http://example.com', $d[0]['url']);
    assertSame('abc', $d[0]['cookie']);

    $r = (string) shell_exec("curl -sS -i '{$base}/index.php?do=keepsession&id={$urlKey}&url=http%3A%2F%2Fexample.com&cookie=abc'");
    assertTrue(str_contains($r, '200'), 'keepsession 200');

    // dedupe: second hit with the same cookie increments instead of inserting
    $r = (string) shell_exec("curl -sS -i '{$base}/index.php?do=api&id={$urlKey}&cookie=abc&location=http%3A%2F%2Fexample.com&toplocation=http%3A%2F%2Fexample.com' -H 'Referer: http://example.com/page'");
    $r = (string) shell_exec("curl -sS '{$base}/index.php?do=do&auth={$p['authCode']}'");
    $d = json_decode($r, true);
    assertSame(1, count($d), 'dedupe keeps one row');

    // missing referer falls back to the platform root
    $r = (string) shell_exec("curl -sS -i '{$base}/index.php?do=api&id={$urlKey}&cookie=xyz&location=http%3A%2F%2Fexample.org'");
    assertTrue(stripos($r, 'Location: http://127.0.0.1:18081') !== false, 'referer fallback');
});
