<?php
require __DIR__ . '/../lib.php';

function patch_register_mode(string $mode): void
{
    $file = '/tmp/xssp-http-installed/config.php';
    $config = [];
    include $file;
    $config['register'] = $mode;
    file_put_contents($file, "<?php\n\$config = " . var_export($config, true) . ";\n");
    // The cli-server may hold an opcache copy of config.php. Move mtime forward so the
    // next revalidation (opcache.revalidate_freq=2) must see the change.
    touch($file, time() + 2);
    usleep(2_200_000);
}

test('registration honours invite and closed modes', function () {
    $base = 'http://127.0.0.1:18081';
    $configFile = '/tmp/xssp-http-installed/config.php';
    $backup = (string) file_get_contents($configFile);
    $jar = '/tmp/xssp-regmode.cookies';
    @unlink($jar);
    $suffix = substr(md5((string) microtime(true)), 0, 6);

    try {
        patch_register_mode('invite');
        $payload = json_encode(['user' => 'invkeyless' . $suffix, 'email' => 'inv1@example.com', 'pwd' => '123456']);
        $r = (string) shell_exec("curl -sS -o /tmp/xssp-regmode.body -w '%{http_code}' -X POST '{$base}/api.php?action=register.submit' -H 'Content-Type: application/json' -d " . escapeshellarg($payload));
        assertSame('400', trim($r), 'invite mode rejects keyless registration');
        $body = (string) file_get_contents('/tmp/xssp-regmode.body');
        assertTrue(str_contains($body, '邀请'), 'invite message: ' . $body);

        $r = (string) shell_exec("curl -sS -c {$jar} -X POST '{$base}/api.php?action=login' -H 'Content-Type: application/json' -d '{\"user\":\"admin\",\"pwd\":\"pw123456\"}'");
        $csrf = (string) (json_decode($r, true)['data']['csrf'] ?? '');
        shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=user.invite.create' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d '{\"isWooyun\":0}'");
        $r = (string) shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=user.invites'");
        $invites = json_decode($r, true)['data']['other'];
        $key = (string) $invites[0]['code'];
        assertTrue($key !== '', 'invite code generated');

        $payload = json_encode([
            'user' => 'inv' . $suffix, 'email' => 'inv' . $suffix . '@example.com',
            'pwd' => '123456', 'pwd2' => '123456', 'key' => $key,
        ]);
        $r = (string) shell_exec("curl -sS -X POST '{$base}/api.php?action=register.submit' -H 'Content-Type: application/json' -d " . escapeshellarg($payload));
        assertTrue(str_contains($r, '"ok":true'), 'invite registration succeeds');

        $payload = json_encode([
            'user' => 'inv2' . $suffix, 'email' => 'inv2' . $suffix . '@example.com',
            'pwd' => '123456', 'pwd2' => '123456', 'key' => $key,
        ]);
        $r = (string) shell_exec("curl -sS -o /tmp/xssp-regmode.body -w '%{http_code}' -X POST '{$base}/api.php?action=register.submit' -H 'Content-Type: application/json' -d " . escapeshellarg($payload));
        assertSame('400', trim($r), 'used invite rejected');

        patch_register_mode('close');
        $payload = json_encode(['user' => 'closed' . $suffix, 'email' => 'closed' . $suffix . '@example.com', 'pwd' => '123456']);
        $r = (string) shell_exec("curl -sS -o /tmp/xssp-regmode.body -w '%{http_code}' -X POST '{$base}/api.php?action=register.submit' -H 'Content-Type: application/json' -d " . escapeshellarg($payload));
        assertSame('400', trim($r), 'closed mode rejects registration');
        $body = (string) file_get_contents('/tmp/xssp-regmode.body');
        assertTrue(str_contains($body, '关闭'), 'closed message: ' . $body);
    } finally {
        file_put_contents($configFile, $backup);
        @unlink('/tmp/xssp-regmode.body');
    }
});
