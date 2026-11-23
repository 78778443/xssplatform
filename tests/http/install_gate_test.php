<?php
require __DIR__ . '/../lib.php';

test('fresh deployment redirects to the installer, tests the db and installs', function () {
    $cfg = include __DIR__ . '/../config.test.php';
    $tmp = '/tmp/xssp-http-fresh';
    $pidFile = '/tmp/xssp-http-fresh.pid';
    exec('rm -rf ' . escapeshellarg($tmp));
    exec('rsync -a --exclude .git --exclude frontend/node_modules --exclude .superpowers '
        . escapeshellarg(dirname(__DIR__, 2)) . '/ ' . escapeshellarg($tmp) . '/');
    @unlink($tmp . '/config.php');
    @unlink($tmp . '/install/install.lock');
    $cmd = 'cd ' . escapeshellarg($tmp) . ' && setsid php -S 127.0.0.1:18082 deploy/router.php > /tmp/xssp-http-fresh.log 2>&1 & echo $!';
    $pid = trim((string) shell_exec($cmd));
    file_put_contents($pidFile, $pid);
    usleep(600000);

    $dbName = 'xssplatform_ui_test';
    $host = $cfg['dbHost'];
    $port = 3306;
    if (str_contains($host, ':')) {
        [$host, $port] = explode(':', $host, 2);
    }
    $cleanup = new mysqli($host, $cfg['dbUser'], $cfg['dbPwd'], null, (int) $port);
    $cleanup->query('DROP DATABASE IF EXISTS ' . $dbName);
    $cleanup->close();

    try {
        $r = (string) shell_exec("curl -sS -i 'http://127.0.0.1:18082/'");
        assertTrue(str_contains($r, 'Location: /install'), 'root redirects to installer');
        $r = (string) shell_exec("curl -sS 'http://127.0.0.1:18082/install'");
        assertTrue(str_contains($r, '<div id="root">'), 'installer shell served');
        $r = (string) shell_exec("curl -sS 'http://127.0.0.1:18082/api.php?action=install.status'");
        assertTrue(str_contains($r, '"installed":false'), 'status reports uninstalled');

        $dbPayload = json_encode([
            'dbHost' => $cfg['dbHost'], 'dbUser' => $cfg['dbUser'], 'dbPwd' => $cfg['dbPwd'],
            'dbName' => $dbName, 'username' => 'admin', 'password' => 'ui&pass123',
            'urlroot' => 'http://127.0.0.1:18082',
        ]);
        $badPayload = json_encode(['dbHost' => '127.0.0.1:1', 'dbUser' => 'root', 'dbPwd' => 'wrong', 'dbName' => 'x']);
        $r = (string) shell_exec("curl -sS -o /tmp/xssp-install-gate.body -w '%{http_code}' -X POST 'http://127.0.0.1:18082/api.php?action=install.testDb' -H 'Content-Type: application/json' -d " . escapeshellarg($badPayload));
        assertSame('400', trim($r), 'bad database credentials rejected');

        $r = (string) shell_exec("curl -sS -X POST 'http://127.0.0.1:18082/api.php?action=install.testDb' -H 'Content-Type: application/json' -d " . escapeshellarg($dbPayload));
        assertTrue(str_contains($r, '"ok":true'), 'database connection test succeeds');

        $r = (string) shell_exec("curl -sS -X POST 'http://127.0.0.1:18082/api.php?action=install.submit' -H 'Content-Type: application/json' -d " . escapeshellarg($dbPayload));
        assertTrue(str_contains($r, '"ok":true'), 'install submit succeeds');
        assertTrue(file_exists($tmp . '/install/install.lock'), 'lock written by api');
        assertTrue(file_exists($tmp . '/config.php'), 'config written by api');

        $r = (string) shell_exec("curl -sS 'http://127.0.0.1:18082/api.php?action=install.status'");
        assertTrue(str_contains($r, '"installed":true'), 'status reports installed');
        $r = (string) shell_exec("curl -sS -o /dev/null -w '%{http_code}' -X POST 'http://127.0.0.1:18082/api.php?action=install.submit' -H 'Content-Type: application/json' -d '{}'");
        assertSame('403', trim($r), 'reinstall rejected after lock');

        $r = (string) shell_exec("curl -sS -o /dev/null -w '%{http_code}' 'http://127.0.0.1:18082/'");
        assertSame('200', trim($r), 'installed root serves the shell');

        // Special-character password installed through the API must be usable.
        $loginPayload = json_encode(['user' => 'admin', 'pwd' => 'ui&pass123']);
        $r = (string) shell_exec("curl -sS -X POST 'http://127.0.0.1:18082/api.php?action=login' -H 'Content-Type: application/json' -d " . escapeshellarg($loginPayload));
        assertTrue(str_contains($r, '"userName":"admin"'), 'installed admin can log in');
        @unlink('/tmp/xssp-install-gate.body');
    } finally {
        $cleanup = new mysqli($host, $cfg['dbUser'], $cfg['dbPwd'], null, (int) $port);
        $cleanup->query('DROP DATABASE IF EXISTS ' . $dbName);
        $cleanup->close();
        if ($pid !== '') {
            exec('kill ' . escapeshellarg($pid) . ' 2>/dev/null');
        }
        @unlink($pidFile);
        exec('rm -rf ' . escapeshellarg($tmp));
    }
});
