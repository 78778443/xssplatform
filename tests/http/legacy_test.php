<?php
require __DIR__ . '/../lib.php';

test('legacy url redirects and old ajax formats', function () {
    $base = 'http://127.0.0.1:18081';

    $r = (string) shell_exec("curl -sS -i '{$base}/index.php?do=login'");
    assertTrue(str_contains($r, 'Location: /login'), 'login redirect');

    $r = (string) shell_exec("curl -sS -i '{$base}/index.php?do=register&key=abc'");
    assertTrue(str_contains($r, 'Location: /register?key=abc'), 'register redirect');

    $r = (string) shell_exec("curl -sS -i '{$base}/install/step1.php'");
    assertTrue(str_contains($r, 'Location: /install'), 'install redirect');

    $uname = 'old' . substr(md5((string) microtime(true)), 0, 8);
    $r = (string) shell_exec("curl -sS -i -X POST '{$base}/index.php?do=register&act=submit' -d 'user={$uname}&email={$uname}@example.com&pwd=123456'");
    assertTrue(str_contains($r, 'Location: /'), 'old register executes');

    $r = (string) shell_exec("curl -sS -X POST '{$base}/index.php?do=register&act=checkue' -d 'user={$uname}&email=x@example.com'");
    assertTrue(preg_match('/^\d+\|\d+\|\d+$/', trim($r)) === 1, 'legacy ajax format');

    $r = (string) shell_exec("curl -sS -i '{$base}/admin/index.php?do=admin_module'");
    assertTrue(str_contains($r, 'Location: /admin/modules'), 'admin redirect');
});
