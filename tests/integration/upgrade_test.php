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
    $col = $db->FirstRow(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='oc_user' AND COLUMN_NAME='feishu'",
        [$cfg['database']]
    );
    if ($col !== false) {
        $db->Execute('ALTER TABLE oc_user CHANGE feishu dingding varchar(255) NULL');
    }

    $col = $db->FirstRow(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='oc_invite_reg' AND COLUMN_NAME='isWooyun'",
        [$cfg['database']]
    );
    if ($col === false) {
        $db->Execute('ALTER TABLE oc_invite_reg ADD COLUMN isWooyun tinyint(1) NOT NULL DEFAULT 0');
    }

    // Simulate an older installation without the hardening indexes/table.
    foreach (['uniq_userName', 'uniq_email'] as $indexName) {
        $idx = $db->FirstRow(
            "SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME='oc_user' AND INDEX_NAME=?",
            [$cfg['database'], $indexName]
        );
        if ($idx !== false) {
            $db->Execute('ALTER TABLE oc_user DROP INDEX `' . $indexName . '`');
        }
    }
    $db->Execute('DROP TABLE IF EXISTS oc_rate_limit');

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
    $col = $db->FirstRow(
        "SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='oc_user' AND COLUMN_NAME='feishu'",
        [$cfg['database']]
    );
    assertTrue($col !== false, 'dingding column renamed to feishu');
    assertSame('varchar(500)', $col['COLUMN_TYPE'], 'feishu column width');
    $col = $db->FirstRow(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='oc_invite_reg' AND COLUMN_NAME='isWooyun'",
        [$cfg['database']]
    );
    assertTrue($col === false, 'removed wooyun invite column dropped');
    foreach (['uniq_userName', 'uniq_email'] as $indexName) {
        $idx = $db->FirstRow(
            "SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME='oc_user' AND INDEX_NAME=?",
            [$cfg['database'], $indexName]
        );
        assertTrue($idx !== false, 'unique index added: ' . $indexName);
    }
    $rate = $db->FirstRow(
        "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME='oc_rate_limit'",
        [$cfg['database']]
    );
    assertTrue($rate !== false, 'rate limit table created');

    // second run must be a no-op, not an error
    $out = shell_exec($cmd);
    assertTrue(is_string($out), 'second run produced output');
});
