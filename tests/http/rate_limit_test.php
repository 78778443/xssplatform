<?php
require __DIR__ . '/../lib.php';

test('login is throttled after repeated failures and recovers after the window', function () {
    $base = 'http://127.0.0.1:18081';
    $jar = '/tmp/xssp-rate.cookies';
    @unlink($jar);

    $db = new mysqli('127.0.0.1', 'root', 'test', 'xssplatform_http', 33061);
    if ($db->connect_errno) {
        skipTest('test database unavailable');
    }
    $db->query('DELETE FROM oc_rate_limit');

    try {
        $codes = [];
        for ($i = 0; $i < 5; $i++) {
            $r = (string) shell_exec(
                "curl -sS -o /tmp/xssp-rate.body -w '%{http_code}' -X POST '{$base}/api.php?action=login'"
                . " -H 'Content-Type: application/json' -d '{\"user\":\"admin\",\"pwd\":\"wrong-pass\"}'"
            );
            $codes[] = trim($r);
        }
        assertSame(['400', '400', '400', '400', '429'], $codes, 'fifth failure blocked');
        $body = (string) file_get_contents('/tmp/xssp-rate.body');
        assertTrue(str_contains($body, '尝试次数过多'), 'throttle message: ' . $body);

        $r = (string) shell_exec(
            "curl -sS -i -X POST '{$base}/api.php?action=login'"
            . " -H 'Content-Type: application/json' -d '{\"user\":\"admin\",\"pwd\":\"pw123456\"}'"
        );
        assertTrue(str_contains($r, ' 429 '), 'correct password is also blocked during lockout');
        assertTrue(stripos($r, 'Retry-After:') !== false, 'Retry-After header present');

        // Simulate the lock window expiring, then the real password must work.
        $db->query('UPDATE oc_rate_limit SET blockedUntil=0,windowStart=0');
        $r = (string) shell_exec(
            "curl -sS -c {$jar} -X POST '{$base}/api.php?action=login'"
            . " -H 'Content-Type: application/json' -d '{\"user\":\"admin\",\"pwd\":\"pw123456\"}'"
        );
        assertTrue(str_contains($r, '"ok":true'), 'login works after the window expires');
    } finally {
        // Do not leave the admin account locked for the rest of the suite.
        $db->query('DELETE FROM oc_rate_limit');
        $db->close();
    }
});
