<?php
require __DIR__ . '/../lib.php';

test('legacy write endpoints require POST, origin and token', function () {
    $base = 'http://127.0.0.1:18081';
    $jar = '/tmp/xssp-legacy-guard.cookies';
    @unlink($jar);
    $r = (string) shell_exec("curl -sS -c {$jar} -X POST '{$base}/api.php?action=login' -H 'Content-Type: application/json' -d '{\"user\":\"admin\",\"pwd\":\"pw123456\"}'");
    $csrf = (string) (json_decode($r, true)['data']['csrf'] ?? '');
    assertTrue($csrf !== '', 'logged in');

    // A legacy write without its old form token is rejected.
    $r = (string) shell_exec("curl -sS -i -b {$jar} -X POST '{$base}/index.php?do=project&act=create_submit' -d 'title=x'");
    assertTrue(str_contains($r, 'Location: /?error=operation_failed'), 'legacy create requires token');

    // ... and works when the token is supplied.
    $r = (string) shell_exec("curl -sS -i -b {$jar} -X POST '{$base}/index.php?do=project&act=create_submit' -d 'title=legacy-ok&token={$csrf}'");
    assertTrue(preg_match('~Location: /project/(\d+)/config\?created=1~', $r, $m) === 1, 'legacy create with token');
    $pid = (int) ($m[1] ?? 0);
    if ($pid > 0) {
        shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=project.delete' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d '{\"id\":{$pid}}'");
    }

    // GET must not mutate state.
    $r = (string) shell_exec("curl -sS -i -b {$jar} '{$base}/index.php?do=user&act=createinvite'");
    assertTrue(!str_contains($r, 'Location: /invites?ok=created'), 'GET write rejected');

    // Cross-origin legacy writes are rejected even with a valid cookie.
    $r = (string) shell_exec("curl -sS -i -b {$jar} -X POST '{$base}/index.php?do=register&act=submit' -H 'Origin: http://evil.example' -d 'user=x&email=x@example.com&pwd=123456'");
    assertTrue(str_contains($r, 'error=no_permission'), 'cross-origin legacy write rejected');

    // Same-origin legacy writes keep working.
    $uname = 'lg' . substr(md5((string) microtime(true)), 0, 8);
    $r = (string) shell_exec("curl -sS -i -X POST '{$base}/index.php?do=register&act=submit' -H 'Origin: http://127.0.0.1:18081' -d 'user={$uname}&email={$uname}@example.com&pwd=123456'");
    assertTrue(str_contains($r, 'Location: /'), 'same-origin legacy register works');
});
