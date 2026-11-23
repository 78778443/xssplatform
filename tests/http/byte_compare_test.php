<?php
require __DIR__ . '/../lib.php';

test('receiver endpoints keep exact legacy bytes', function () {
    $base = 'http://127.0.0.1:18081';
    $r = (string) shell_exec("curl -sS '{$base}/index.php?do=code&urlKey=FIXTUREKEY'");
    assertSame('var x="a";alert(1);', trim($r), 'code endpoint bytes');

    $r = (string) shell_exec("curl -sS '{$base}/index.php?do=do&auth=FIXTUREAUTH'");
    assertSame(
        '[{"url":null,"cookie":null},{"url":"http:\/\/example.com","cookie":"abc"}]',
        trim($r),
        'do endpoint json keeps legacy nulls for uncollected keys'
    );
});
