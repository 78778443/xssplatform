<?php
require __DIR__ . '/../lib.php';

test('fresh deployment redirects to the installer and reports install status', function () {
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

    try {
        $r = (string) shell_exec("curl -sS -i 'http://127.0.0.1:18082/'");
        assertTrue(str_contains($r, 'Location: /install'), 'root redirects to installer');
        $r = (string) shell_exec("curl -sS 'http://127.0.0.1:18082/install'");
        assertTrue(str_contains($r, '<div id="root">'), 'installer shell served');
        $r = (string) shell_exec("curl -sS 'http://127.0.0.1:18082/api.php?action=install.status'");
        assertTrue(str_contains($r, '"installed":false'), 'status reports uninstalled');
    } finally {
        if ($pid !== '') {
            exec('kill ' . escapeshellarg($pid) . ' 2>/dev/null');
        }
        @unlink($pidFile);
        exec('rm -rf ' . escapeshellarg($tmp));
    }
});
