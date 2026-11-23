<?php
define('IN_OLDCMS', true);
require __DIR__ . '/../lib.php';
require __DIR__ . '/../../source/class/DB.class.php';

$cfg = include __DIR__ . '/../config.test.php';

test('keepsession cron skips private and non-http targets', function () use ($cfg) {
    $db = new DB_Mysql();
    assertTrue($db->Connect($cfg['dbHost'], $cfg['dbUser'], $cfg['dbPwd'], $cfg['database'], $cfg['charset'], $cfg['tbPrefix']));
    $db->Execute('DELETE FROM oc_keepsession');
    $db->Insert('oc_keepsession', [
        'userId' => 1, 'projectId' => 1, 'url' => 'http://127.0.0.1:33061/',
        'cookie' => 'a=1', 'hash' => 'h1', 'addTime' => 1, 'updateTime' => 0,
    ]);
    $db->Insert('oc_keepsession', [
        'userId' => 1, 'projectId' => 1, 'url' => 'file:///etc/passwd',
        'cookie' => 'a=1', 'hash' => 'h2', 'addTime' => 1, 'updateTime' => 0,
    ]);

    $cmd = 'XSSP_CONFIG=' . escapeshellarg(realpath(__DIR__ . '/../config.test.php'))
        . ' php ' . escapeshellarg(dirname(__DIR__, 2) . '/scripts/keepsession.php') . ' 2>&1';
    shell_exec($cmd);

    $times = $db->FirstColumn('SELECT updateTime FROM oc_keepsession ORDER BY id');
    assertSame([0, 0], array_map('intval', $times), 'unsafe rows were not fetched');
    $db->Execute('DELETE FROM oc_keepsession');
});
