<?php
require __DIR__ . '/../lib.php';

test('admin audits public modules and legacy audit stays guarded', function () {
    $base = 'http://127.0.0.1:18081';
    $adminJar = '/tmp/xssp-audit-admin.cookies';
    $userJar = '/tmp/xssp-audit-user.cookies';
    @unlink($adminJar);
    @unlink($userJar);

    $r = (string) shell_exec("curl -sS -c {$adminJar} -X POST '{$base}/api.php?action=login' -H 'Content-Type: application/json' -d '{\"user\":\"admin\",\"pwd\":\"pw123456\"}'");
    $adminCsrf = (string) (json_decode($r, true)['data']['csrf'] ?? '');

    $uname = 'aud' . substr(md5((string) microtime(true)), 0, 8);
    $r = (string) shell_exec("curl -sS -c {$userJar} -X POST '{$base}/api.php?action=register.submit' -H 'Content-Type: application/json' -d '{\"user\":\"{$uname}\",\"email\":\"{$uname}@example.com\",\"pwd\":\"123456\"}'");
    $userCsrf = (string) (json_decode($r, true)['data']['csrf'] ?? '');
    assertTrue($userCsrf !== '', 'regular user registered');

    $module = '{"title":"audit-pending","description":"","keys":["cookie"],"setkeys":[],"code":"x=1","isOpen":1}';
    $r = (string) shell_exec("curl -sS -b {$userJar} -X POST '{$base}/api.php?action=module.create' -H 'X-CSRF-Token: {$userCsrf}' -H 'Content-Type: application/json' -d " . escapeshellarg($module));
    $mid = (int) (json_decode($r, true)['data']['id'] ?? 0);
    assertTrue($mid > 0, 'public module created');

    $r = (string) shell_exec("curl -sS -b {$adminJar} '{$base}/api.php?action=admin.modules&pNO=1'");
    $items = json_decode($r, true)['data']['items'];
    $found = null;
    foreach ($items as $item) {
        if ((int) $item['id'] === $mid) {
            $found = $item;
        }
    }
    assertTrue($found !== null, 'module listed for admin');
    assertSame(0, (int) $found['isAudit'], 'starts pending');

    // A regular user cannot audit.
    $r = (string) shell_exec("curl -sS -o /dev/null -w '%{http_code}' -b {$userJar} -X POST '{$base}/api.php?action=admin.module.audit' -H 'X-CSRF-Token: {$userCsrf}' -H 'Content-Type: application/json' -d '{\"id\":{$mid},\"isAudit\":1}'");
    assertSame('403', trim($r), 'regular user audit forbidden');

    $r = (string) shell_exec("curl -sS -b {$adminJar} -X POST '{$base}/api.php?action=admin.module.audit' -H 'X-CSRF-Token: {$adminCsrf}' -H 'Content-Type: application/json' -d '{\"id\":{$mid},\"isAudit\":1}'");
    assertTrue(str_contains($r, '"ok":true'), 'admin audit');

    $r = (string) shell_exec("curl -sS -b {$adminJar} '{$base}/api.php?action=modules.list'");
    assertTrue(str_contains($r, 'audit-pending'), 'approved module appears in list');

    // Legacy dashboard redirect and guarded audit.
    $r = (string) shell_exec("curl -sS -i -b {$adminJar} '{$base}/admin/index.php'");
    assertTrue(str_contains($r, 'Location: /admin'), 'legacy admin default redirect');
    $r = (string) shell_exec("curl -sS -i -b {$adminJar} '{$base}/admin/index.php?do=admin_module&act=audit&id={$mid}&isAudit=0'");
    assertTrue(str_contains($r, 'error=method_not_allowed'), 'legacy audit rejects GET');
    $r = (string) shell_exec("curl -sS -i -b {$adminJar} -X POST '{$base}/admin/index.php?do=admin_module&act=audit&id={$mid}&isAudit=0' -H 'Origin: http://127.0.0.1:18081'");
    assertTrue(str_contains($r, 'error=operation_failed'), 'legacy audit requires its form token');
    $r = (string) shell_exec("curl -sS -i -b {$adminJar} -X POST '{$base}/admin/index.php?do=admin_module&act=audit' -H 'Origin: http://127.0.0.1:18081' -d 'id={$mid}&isAudit=0&token={$adminCsrf}'");
    assertTrue(str_contains($r, 'Location: /admin/modules?ok=audited'), 'legacy audit works with POST and token');
});
