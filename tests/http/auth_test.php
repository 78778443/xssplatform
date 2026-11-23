<?php
require __DIR__ . '/../lib.php';

test('login sets a hardened cookie and session, logout protects csrf', function () {
    $base = 'http://127.0.0.1:18081';
    $jar = '/tmp/xssp-auth.cookies';
    @unlink($jar);

    $r = (string) shell_exec("curl -sS -c {$jar} -b {$jar} -i -X POST '{$base}/api.php?action=login' -H 'Content-Type: application/json' -d '{\"user\":\"admin\",\"pwd\":\"pw123456\",\"auto\":0}'");
    assertTrue(str_contains($r, 'HTTP/1.1 200'), 'login 200');
    assertTrue(str_contains($r, 'HttpOnly'), 'cookie httponly');
    assertTrue(str_contains($r, 'SameSite=Lax'), 'cookie samesite');
    $json = json_decode(substr($r, strpos($r, "\r\n\r\n") + 4), true);
    assertSame('admin', $json['data']['user']['userName']);
    assertSame(32, strlen($json['data']['csrf']), 'csrf length');
    $csrf = $json['data']['csrf'];

    $r = (string) shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=session'");
    $json = json_decode($r, true);
    assertSame('admin', $json['data']['user']['userName']);

    $r = (string) shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=logout' -H 'X-CSRF-Token: {$csrf}'");
    assertTrue(str_contains($r, '"ok":true'), 'logout');
    $r = (string) shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=session'");
    $json = json_decode($r, true);
    assertSame(null, $json['data']['user'], 'logged out');
});

test('registration validates duplicates and keeps the legacy message', function () {
    $base = 'http://127.0.0.1:18081';
    $uname = 'newuser' . substr(md5((string) microtime(true)), 0, 6);
    $r = (string) shell_exec("curl -sS -X POST '{$base}/api.php?action=register.submit' -H 'Content-Type: application/json' -d '{\"user\":\"{$uname}\",\"email\":\"{$uname}@example.com\",\"pwd\":\"123456\"}'");
    assertTrue(str_contains($r, '"ok":true'), 'register');

    $r = (string) shell_exec("curl -sS -X POST '{$base}/api.php?action=register.check' -H 'Content-Type: application/json' -d '{\"user\":\"{$uname}\",\"email\":\"other@example.com\",\"key\":\"\"}'");
    assertTrue(str_contains($r, '"userExisted":1'), 'duplicate user');

    $r = (string) shell_exec("curl -sS -X POST '{$base}/api.php?action=login' -H 'Content-Type: application/json' -d '{\"user\":\"admin\",\"pwd\":\"wrong\"}'");
    assertTrue(str_contains($r, '登录失败,请检查用户/邮箱或密码'), 'legacy message');
});
