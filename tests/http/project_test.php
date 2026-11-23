<?php
require __DIR__ . '/../lib.php';

test('project lifecycle keeps legacy semantics', function () {
    $base = 'http://127.0.0.1:18081';
    $jar = '/tmp/xssp-proj.cookies';
    @unlink($jar);
    $r = (string) shell_exec("curl -sS -c {$jar} -X POST '{$base}/api.php?action=login' -H 'Content-Type: application/json' -d '{\"user\":\"admin\",\"pwd\":\"pw123456\"}'");
    $csrf = json_decode($r, true)['data']['csrf'];

    $r = (string) shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=project.create' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d '{\"title\":\"p1\",\"description\":\"d1\"}'");
    $data = json_decode($r, true)['data'];
    assertTrue($data['id'] > 0);
    assertSame(6, strlen((string) $data['urlKey']), 'urlKey length');
    assertSame(32, strlen((string) $data['authCode']), 'authCode length');

    $r = (string) shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=dashboard'");
    $dash = json_decode($r, true)['data'];
    assertTrue(count($dash['projects']) >= 1);

    $r = (string) shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=project.get&id={$data['id']}'");
    $get = json_decode($r, true)['data'];
    assertSame('p1', $get['project']['title']);
    assertSame([], $get['contents']);

    $r = (string) shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=project.setcode.get&id={$data['id']}'");
    $sc = json_decode($r, true)['data'];
    assertTrue(count($sc['modulesCan']) >= 1);

    $payload = '{"id":' . $data['id'] . ',"ty":"create","modules":[1],"code":"alert(1)","setkey_1_keepsession":"1"}';
    $r = (string) shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=project.setcode.save' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d " . escapeshellarg($payload));
    assertTrue(str_contains($r, '"ok":true'), 'setcode save');

    $r = (string) shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=project.viewcode&id={$data['id']}'");
    $vc = json_decode($r, true)['data'];
    assertTrue(str_contains($vc['code'], 'alert(1)'));
    assertTrue(str_contains($vc['codeurl'], $data['urlKey']));
    assertTrue(str_contains($vc['scriptShow1'], '<script src='));

    $r = (string) shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=project.delete' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d " . escapeshellarg('{"id":' . $data['id'] . '}'));
    assertTrue(str_contains($r, '"ok":true'));
});
