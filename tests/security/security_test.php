<?php
require __DIR__ . '/../lib.php';

test('csrf, origin, auth, injection and install guards', function () {
    $base = 'http://127.0.0.1:18081';
    $jar = '/tmp/xssp-sec.cookies';
    @unlink($jar);
    $r = (string) shell_exec("curl -sS -c {$jar} -X POST '{$base}/api.php?action=login' -H 'Content-Type: application/json' -d '{\"user\":\"admin\",\"pwd\":\"pw123456\"}'");
    $csrf = json_decode($r, true)['data']['csrf'];

    // missing CSRF
    $r = (string) shell_exec("curl -sS -o /dev/null -w '%{http_code}' -b {$jar} -X POST '{$base}/api.php?action=project.create' -H 'Content-Type: application/json' -d '{\"title\":\"x\",\"description\":\"\"}'");
    assertSame('419', trim($r), 'missing csrf rejected');

    // cross origin
    $r = (string) shell_exec("curl -sS -o /dev/null -w '%{http_code}' -b {$jar} -X POST '{$base}/api.php?action=project.create' -H 'Origin: http://evil.com' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d '{\"title\":\"x\",\"description\":\"\"}'");
    assertSame('403', trim($r), 'cross origin rejected');

    // unauthenticated
    $r = (string) shell_exec("curl -sS -o /dev/null -w '%{http_code}' '{$base}/api.php?action=dashboard'");
    assertSame('401', trim($r), 'unauthenticated 401');

    // sql injection payloads must not leak SQL errors
    foreach ([
        '/api.php?action=project.get&id=1%27+OR+1%3D1',
        '/index.php?do=code&urlKey=%27+OR+1%3D1',
        '/index.php?do=do&auth=%27+OR+1%3D1',
        '/index.php?do=keepsession&id=%27+OR+1%3D1&url=x&cookie=y',
    ] as $u) {
        $r = (string) shell_exec("curl -sS -i '{$base}{$u}'");
        assertFalse(
            str_contains($r, 'MySQL') || str_contains($r, 'Warning') || str_contains($r, 'Fatal'),
            'no sql error leak: ' . $u
        );
    }

    // another user's project is 404
    $db = new mysqli('127.0.0.1', 'root', 'test', 'xssplatform_http', 33061);
    $db->query("INSERT INTO oc_user (adminLevel,userName,userPwd,email,addTime) VALUES (0,'user2','x','u2@example.com',1)");
    $uid2 = $db->insert_id;
    $db->query("INSERT INTO oc_project (userId,title,description,modules,moduleSetKeys,code,urlKey,authCode,addTime) VALUES ($uid2,'other','','[]','[]','','aBcDeF','" . str_repeat('a', 32) . "',1)");
    $pid2 = $db->insert_id;
    $r = (string) shell_exec("curl -sS -o /dev/null -w '%{http_code}' -b {$jar} '{$base}/api.php?action=project.get&id={$pid2}'");
    assertSame('404', trim($r), 'other user project 404');
    $db->close();

    // reinstall
    $r = (string) shell_exec("curl -sS -o /dev/null -w '%{http_code}' -X POST '{$base}/api.php?action=install.submit' -H 'Content-Type: application/json' -d '{}'");
    assertSame('403', trim($r), 'reinstall rejected');

    // Host header is not reflected into the redirect target or the body
    $r = (string) shell_exec("curl -sS -i -H 'Host: evil.com' '{$base}/install/step1.php'");
    $body = substr($r, strpos($r, "\r\n\r\n") + 4);
    assertTrue(str_contains($r, 'Location: /install'), 'relative install redirect');
    assertFalse(str_contains($body, 'evil.com'), 'host not reflected in body');

    // non-http(s) Referer cannot become the redirect target
    $r = (string) shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=project.create' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d '{\"title\":\"redir\",\"description\":\"\"}'");
    $p = json_decode($r, true)['data'];
    $r = (string) shell_exec("curl -sS -i '{$base}/index.php?do=api&id={$p['urlKey']}&cookie=y' -H 'Referer: javascript:alert(1)'");
    assertFalse(str_contains($r, 'javascript:alert(1)'), 'bad referer scheme rejected');
    assertTrue(str_contains($r, 'Location: http://127.0.0.1:18081'), 'fallback to urlroot');

    // CLI maintenance scripts and tooling are not web-executable
    foreach (['/scripts/keepsession.php', '/install/upgrade.php', '/tests/config.test.php', '/deploy/router.php', '/config.php'] as $u) {
        $r = (string) shell_exec("curl -sS -o /dev/null -w '%{http_code}' '{$base}{$u}'");
        assertSame('404', trim($r), 'not web accessible: ' . $u);
    }

    // invalid Feishu webhook is rejected (only official bot URLs are accepted)
    $r = (string) shell_exec("curl -sS -o /dev/null -w '%{http_code}' -b {$jar} -X POST '{$base}/api.php?action=user.settings.save' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d '{\"emsg\":\"0\",\"fmsg\":\"0\",\"feishu\":\"http://127.0.0.1:19090/send\"}'");
    assertSame('400', trim($r), 'non-feishu webhook rejected');

    // unknown API action
    $r = (string) shell_exec("curl -sS -o /dev/null -w '%{http_code}' '{$base}/api.php?action=nope'");
    assertSame('404', trim($r), 'unknown action 404');

    // The configured urlroot is 127.0.0.1 but browsers may use localhost; same-origin
    // must accept the request host while still rejecting a foreign Origin.
    $lhJar = '/tmp/xssp-sec-localhost.cookies';
    @unlink($lhJar);
    $lh = '--resolve localhost:18081:127.0.0.1';
    $r = (string) shell_exec("curl -sS {$lh} -c {$lhJar} -X POST 'http://localhost:18081/api.php?action=login' -H 'Content-Type: application/json' -d '{\"user\":\"admin\",\"pwd\":\"pw123456\"}'");
    $lhCsrf = (string) (json_decode($r, true)['data']['csrf'] ?? '');
    assertTrue($lhCsrf !== '', 'login through localhost');
    $r = (string) shell_exec("curl -sS {$lh} -b {$lhJar} -H 'Referer: http://localhost:18081/' 'http://localhost:18081/api.php?action=dashboard'");
    assertTrue(str_contains($r, '"ok":true'), 'localhost origin accepted: ' . substr($r, 0, 160));
    $r = (string) shell_exec("curl -sS {$lh} -b {$lhJar} -H 'Origin: http://evil.example' -X POST 'http://localhost:18081/api.php?action=project.create' -H 'X-CSRF-Token: {$lhCsrf}' -H 'Content-Type: application/json' -d '{\"title\":\"x\",\"description\":\"\"}'");
    assertTrue(str_contains($r, 'no_permission'), 'cross origin still rejected with alternate host');

    // security headers
    $r = (string) shell_exec("curl -sS -i '{$base}/'");
    assertTrue(str_contains($r, 'X-Content-Type-Options: nosniff'), 'nosniff header');
    assertTrue(str_contains($r, 'Content-Security-Policy'), 'csp header');
});
