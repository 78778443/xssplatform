<?php
require __DIR__ . '/../lib.php';

test('project setkey values survive edit round trips without escaping drift', function () {
    $base = 'http://127.0.0.1:18081';
    $jar = '/tmp/xssp-setkey.cookies';
    @unlink($jar);
    $r = (string) shell_exec("curl -sS -c {$jar} -X POST '{$base}/api.php?action=login' -H 'Content-Type: application/json' -d '{\"user\":\"admin\",\"pwd\":\"pw123456\"}'");
    $csrf = (string) (json_decode($r, true)['data']['csrf'] ?? '');

    $r = (string) shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=module.create' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d " . escapeshellarg('{"title":"setkey","description":"","keys":["cookie"],"setkeys":["info"],"code":"x={set.info};","isOpen":0}'));
    $mid = (int) (json_decode($r, true)['data']['id'] ?? 0);
    assertTrue($mid > 0, 'module created');

    $r = (string) shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=project.create' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d '{\"title\":\"setkey\",\"description\":\"\"}'");
    $pid = (int) (json_decode($r, true)['data']['id'] ?? 0);
    assertTrue($pid > 0, 'project created');

    $field = 'setkey_' . $mid . '_info';
    $save = '{"id":' . $pid . ',"ty":"edit","modules":[' . $mid . '],"code":"","' . $field . '":"a&b"}';
    shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=project.setcode.save' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d " . escapeshellarg($save));

    for ($i = 0; $i < 2; $i++) {
        $r = (string) shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=project.setcode.get&id={$pid}'");
        $data = json_decode($r, true)['data'];
        $value = null;
        foreach ($data['modulesCan'] as $module) {
            if ((int) $module['id'] === $mid) {
                foreach ($module['setkeys'] as $item) {
                    if ($item['key'] === 'info') {
                        $value = $item['value'];
                    }
                }
            }
        }
        assertSame('a&b', $value, 'setkey value stable on read ' . $i);
        shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=project.setcode.save' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d " . escapeshellarg($save));
    }

    shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=project.delete' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d '{\"id\":{$pid}}'");
});
