<?php
require __DIR__ . '/../lib.php';

test('receiver notifications use smtp without leaking debug output', function () {
    $mailhog = @fsockopen('127.0.0.1', 8025, $errno, $errstr, 2);
    $mock = @fsockopen('127.0.0.1', 19090, $errno, $errstr, 2);
    if (!is_resource($mailhog) || !is_resource($mock)) {
        skipTest('MailHog (8025) or mock DingTalk (19090) not running');
    }
    fclose($mailhog);
    fclose($mock);
    $base = 'http://127.0.0.1:18081';
    $jar = '/tmp/xssp-notify.cookies';
    @unlink($jar);
    @unlink('/tmp/dingding-last.json');
    $r = (string) shell_exec("curl -sS -c {$jar} -X POST '{$base}/api.php?action=login' -H 'Content-Type: application/json' -d '{\"user\":\"admin\",\"pwd\":\"pw123456\"}'");
    $csrf = json_decode($r, true)['data']['csrf'];

    $payload = '{"emsg":"1","dmsg":"1","dingding":"test-key"}';
    $r = (string) shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=user.settings.save' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d " . escapeshellarg($payload));
    assertTrue(str_contains($r, '"ok":true'), 'settings saved');

    $r = (string) shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=project.create' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d '{\"title\":\"notify\",\"description\":\"\"}'");
    $p = json_decode($r, true)['data'];

    $r = (string) shell_exec("curl -sS -i '{$base}/index.php?do=api&id={$p['urlKey']}&cookie=abc&location=http%3A%2F%2Fexample.com&toplocation=http%3A%2F%2Fexample.com' -H 'Referer: http://example.com/'");
    $body = trim(substr($r, strpos($r, "\r\n\r\n") + 4));
    assertSame('', $body, 'smtp debug must not leak into response');

    sleep(1);
    $mail = (string) shell_exec('curl -sS http://127.0.0.1:8025/api/v2/messages');
    assertTrue(str_contains($mail, 'example.com'), 'mail delivered');
    $ding = (string) shell_exec('curl -sS http://127.0.0.1:19090/last');
    assertTrue(str_contains($ding, 'test-key') || str_contains($ding, 'Cookie'), 'ding delivered');
});
