<?php
require __DIR__ . '/../lib.php';

test('settings persist feishu and admin sees overview/modules', function () {
    $base = 'http://127.0.0.1:18081';
    $jar = '/tmp/xssp-ua.cookies';
    @unlink($jar);
    $r = (string) shell_exec("curl -sS -c {$jar} -X POST '{$base}/api.php?action=login' -H 'Content-Type: application/json' -d '{\"user\":\"admin\",\"pwd\":\"pw123456\"}'");
    $csrf = json_decode($r, true)['data']['csrf'];

    $payload = '{"emsg":"1","fmsg":"1","feishu":"https://open.feishu.cn/open-apis/bot/v2/hook/ding-key"}';
    $r = (string) shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=user.settings.save' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d " . escapeshellarg($payload));
    assertTrue(str_contains($r, '"ok":true'), 'settings save');
    $r = (string) shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=user.settings.get'");
    $s = json_decode($r, true)['data'];
    assertSame('https://open.feishu.cn/open-apis/bot/v2/hook/ding-key', $s['feishu']);
    assertSame('1', $s['emsg']);
    assertSame('1', $s['fmsg']);

    $r = (string) shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=user.invite.create' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d '{\"isWooyun\":0}'");
    assertTrue(str_contains($r, '"ok":true'), 'invite create');
    $r = (string) shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=user.invites'");
    $inv = json_decode($r, true)['data'];
    assertTrue(count($inv['other']) >= 1, 'invite listed');

    $r = (string) shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=admin.overview'");
    $o = json_decode($r, true)['data'];
    assertTrue($o['userCount'] >= 1);
    assertTrue(isset($o['sysInfo']['phpVersion']));

    $r = (string) shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=admin.modules&pNO=1'");
    assertTrue(str_contains($r, '"items"'), 'admin modules list');
});
