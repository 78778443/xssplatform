<?php
require __DIR__ . '/../lib.php';

test('received content can be deleted one by one and in bulk', function () {
    $base = 'http://127.0.0.1:18081';
    $jar = '/tmp/xssp-content-del.cookies';
    @unlink($jar);
    $r = (string) shell_exec("curl -sS -c {$jar} -X POST '{$base}/api.php?action=login' -H 'Content-Type: application/json' -d '{\"user\":\"admin\",\"pwd\":\"pw123456\"}'");
    $csrf = (string) (json_decode($r, true)['data']['csrf'] ?? '');

    $r = (string) shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=project.create' -H " . "'X-CSRF-Token: {$csrf}'" . " -H 'Content-Type: application/json' -d '{\"title\":\"content-del\",\"description\":\"\"}'");
    $p = json_decode($r, true)['data'];
    $key = (string) $p['urlKey'];

    shell_exec("curl -sS '{$base}/index.php?do=api&id={$key}&cookie=a&location=http%3A%2F%2Fa.example'");
    shell_exec("curl -sS '{$base}/index.php?do=api&id={$key}&cookie=b&location=http%3A%2F%2Fb.example'");
    shell_exec("curl -sS '{$base}/index.php?do=api&id={$key}&cookie=c&location=http%3A%2F%2Fc.example'");

    $r = (string) shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=project.get&id={$p['id']}'");
    $contents = json_decode($r, true)['data']['contents'];
    assertSame(3, count($contents), 'three rows received');
    $first = (int) $contents[0]['id'];

    $r = (string) shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=project.contents.delete' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d '{\"id\":{$first}}'");
    assertTrue(str_contains($r, '"ok":true'), 'delete one');
    $r = (string) shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=project.get&id={$p['id']}'");
    $contents = json_decode($r, true)['data']['contents'];
    assertSame(2, count($contents), 'one row left after single delete');

    $ids = array_map('intval', array_column($contents, 'id'));
    $payload = json_encode(['ids' => $ids]);
    $r = (string) shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=project.contents.deleteMany' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d " . escapeshellarg($payload));
    assertTrue(str_contains($r, '"ok":true'), 'delete many');
    $r = (string) shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=project.get&id={$p['id']}'");
    assertSame(0, count(json_decode($r, true)['data']['contents']), 'all rows deleted');

    shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=project.delete' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d '{\"id\":{$p['id']}}'");
});
