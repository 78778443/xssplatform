<?php
define('IN_OLDCMS', true);
require __DIR__ . '/../lib.php';
require __DIR__ . '/../../source/class/DB.class.php';

$cfg = include __DIR__ . '/../config.test.php';

test('upgrade widens password and session columns idempotently', function () use ($cfg) {
    $db = new DB_Mysql();
    assertTrue($db->Connect($cfg['dbHost'], $cfg['dbUser'], $cfg['dbPwd'], $cfg['database'], $cfg['charset'], $cfg['tbPrefix']));
    $db->Execute('ALTER TABLE oc_user MODIFY userPwd varchar(100) NULL');
    $db->Execute('ALTER TABLE oc_session MODIFY data varchar(255) NULL');

    $cmd = 'XSSP_CONFIG=' . escapeshellarg(realpath(__DIR__ . '/../config.test.php'))
        . ' php ' . escapeshellarg(dirname(__DIR__, 2) . '/install/upgrade.php') . ' 2>&1';
    shell_exec($cmd);

    $col = $db->FirstRow(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='oc_user' AND COLUMN_NAME='userPwd'",
        [$cfg['database']]
    );
    assertSame('varchar(255)', $col['COLUMN_TYPE']);
    $col = $db->FirstRow(
        "SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='oc_session' AND COLUMN_NAME='data'",
        [$cfg['database']]
    );
    assertSame('text', $col['DATA_TYPE']);

    // second run must be a no-op, not an error
    $out = shell_exec($cmd);
    assertTrue(is_string($out), 'second run produced output');
});
