<?php
require __DIR__ . '/../lib.php';

test('private modules of another user cannot be selected or rendered', function () {
    $base = 'http://127.0.0.1:18081';
    $j2 = '/tmp/xssp-vis-u2.cookies';
    $j1 = '/tmp/xssp-vis-u1.cookies';
    @unlink($j2);
    @unlink($j1);

    $name = 'vis' . substr(md5((string) microtime(true)), 0, 8);
    $r = (string) shell_exec("curl -sS -c {$j2} -X POST '{$base}/api.php?action=register.submit' -H 'Content-Type: application/json' -d " . escapeshellarg('{"user":"' . $name . '","email":"' . $name . '@example.com","pwd":"pw123456","pwd2":"pw123456","key":""}'));
    assertTrue(str_contains($r, '"ok":true'), 'registered second user');
    $csrf2 = (string) (json_decode($r, true)['data']['csrf'] ?? '');
    assertTrue($csrf2 !== '', 'second user csrf');

    $r = (string) shell_exec("curl -sS -b {$j2} -X POST '{$base}/api.php?action=module.create' -H 'X-CSRF-Token: {$csrf2}' -H 'Content-Type: application/json' -d " . escapeshellarg('{"title":"私有","description":"","keys":["cookie"],"setkeys":[],"code":"FOREIGNCODE","isOpen":0}'));
    $mid = (int) (json_decode($r, true)['data']['id'] ?? 0);
    assertTrue($mid > 0, 'private module created');

    $r = (string) shell_exec("curl -sS -c {$j1} -X POST '{$base}/api.php?action=login' -H 'Content-Type: application/json' -d '{\"user\":\"admin\",\"pwd\":\"pw123456\"}'");
    $csrf1 = (string) (json_decode($r, true)['data']['csrf'] ?? '');
    $r = (string) shell_exec("curl -sS -b {$j1} -X POST '{$base}/api.php?action=project.create' -H 'X-CSRF-Token: {$csrf1}' -H 'Content-Type: application/json' -d '{\"title\":\"vis\",\"description\":\"\"}'");
    $project = json_decode($r, true)['data'] ?? [];

    $payload = '{"id":' . (int) $project['id'] . ',"ty":"edit","modules":[' . $mid . '],"code":""}';
    $r = (string) shell_exec("curl -sS -b {$j1} -X POST '{$base}/api.php?action=project.setcode.save' -H 'X-CSRF-Token: {$csrf1}' -H 'Content-Type: application/json' -d " . escapeshellarg($payload));
    assertTrue(str_contains($r, '"ok":true'), 'save accepted');

    $pid = (int) $project['id'];
    $urlKey = urlencode((string) $project['urlKey']);
    $r = (string) shell_exec("curl -sS -b {$j1} '{$base}/api.php?action=project.viewcode&id={$pid}'");
    assertTrue(!str_contains($r, 'FOREIGNCODE'), 'viewcode hides foreign private module');

    $r = (string) shell_exec("curl -sS '{$base}/index.php?do=code&urlKey={$urlKey}'");
    assertTrue(!str_contains($r, 'FOREIGNCODE'), 'receiver hides foreign private module');

    shell_exec("curl -sS -b {$j1} -X POST '{$base}/api.php?action=project.delete' -H 'X-CSRF-Token: {$csrf1}' -H 'Content-Type: application/json' -d " . escapeshellarg('{"id":' . $pid . '}'));
});
