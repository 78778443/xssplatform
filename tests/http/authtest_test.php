<?php
require __DIR__ . '/../lib.php';

test('authtest endpoint challenges and forwards decoy credentials', function () {
    $base = 'http://127.0.0.1:18081';

    $r = (string) shell_exec("curl -sS -i '{$base}/authtest.php?id=42&info=Demo%20Maintenance'");
    assertTrue(str_contains($r, ' 401 '), '401 challenge');
    assertTrue(stripos($r, 'WWW-Authenticate: Basic realm="Demo Maintenance"') !== false, 'basic realm');

    $r = (string) shell_exec("curl -sS -i -u alice:s3cret '{$base}/authtest.php?id=42&info=Demo'");
    assertTrue(str_contains($r, ' 302 '), '302 after basic auth');
    assertTrue(
        str_contains($r, 'Location: http://127.0.0.1:18081/index.php?do=api&id=42&username=alice&password=s3cret'),
        'credentials forwarded to the receiver'
    );

    // Header injection attempts are stripped from the realm and never reflected raw.
    $r = (string) shell_exec("curl -sS -i '{$base}/authtest.php?id=1&info=x%0d%0aX-Injected:%20yes'");
    $body = substr($r, strpos($r, "\r\n\r\n") + 4);
    assertFalse(str_contains($body, 'X-Injected'), 'CRLF stripped from realm');
});
