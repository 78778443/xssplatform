<?php
require __DIR__ . '/../lib.php';

test('module lifecycle and visibility', function () {
    $base = 'http://127.0.0.1:18081';
    $jar = '/tmp/xssp-mod.cookies';
    @unlink($jar);
    $r = (string) shell_exec("curl -sS -c {$jar} -X POST '{$base}/api.php?action=login' -H 'Content-Type: application/json' -d '{\"user\":\"admin\",\"pwd\":\"pw123456\"}'");
    $csrf = json_decode($r, true)['data']['csrf'];

    $payload = '{"title":"m1","description":"d","keys":["cookie","location"],"setkeys":["url"],"code":"x=1","isOpen":0}';
    $r = (string) shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=module.create' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d " . escapeshellarg($payload));
    $id = json_decode($r, true)['data']['id'];
    assertTrue($id > 0);

    $r = (string) shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=module.get&id={$id}'");
    $m = json_decode($r, true)['data'];
    assertSame('m1', $m['module']['title']);
    assertSame(['cookie', 'location'], $m['keys']);
    assertSame(['url'], $m['setkeys']);
    assertTrue($m['canEdit']);

    $payload = '{"id":' . $id . ',"title":"m2","description":"d","keys":["cookie"],"setkeys":[],"code":"x=2","isOpen":1}';
    $r = (string) shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=module.save' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d " . escapeshellarg($payload));
    assertTrue(str_contains($r, '"ok":true'));

    $r = (string) shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=modules.list'");
    assertTrue(str_contains($r, 'm2'));

    $r = (string) shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=module.delete' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d " . escapeshellarg('{"id":' . $id . '}'));
    assertTrue(str_contains($r, '"ok":true'));
});
