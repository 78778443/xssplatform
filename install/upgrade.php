<?php
/**
 * Idempotent database upgrade for existing installations.
 * Usage: XSSP_CONFIG=/path/to/config.php php install/upgrade.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('ROOT_PATH', dirname(__DIR__));
define('IN_OLDCMS', true);

require ROOT_PATH . '/source/bootstrap.php';

$db = db();
$schema = (string) Config::get('database', '');
$userTable = Tb('user');
$sessionTable = Tb('session');

$col = $db->FirstRow(
    'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?',
    [$schema, $userTable, 'userPwd']
);
if ($col !== false && stripos((string) $col['COLUMN_TYPE'], 'varchar(255)') === false) {
    $db->Execute('ALTER TABLE `' . $userTable . '` MODIFY userPwd varchar(255) NULL');
    echo "altered {$userTable}.userPwd to varchar(255)\n";
}

$col = $db->FirstRow(
    'SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?',
    [$schema, $sessionTable, 'data']
);
if ($col !== false && strtolower((string) $col['DATA_TYPE']) !== 'text') {
    $db->Execute('ALTER TABLE `' . $sessionTable . '` MODIFY data TEXT');
    echo "altered {$sessionTable}.data to TEXT\n";
}

// DingTalk was replaced by Feishu webhooks; rename the column (or add it for very old schemas).
$col = $db->FirstRow(
    'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?',
    [$schema, $userTable, 'feishu']
);
if ($col === false) {
    $old = $db->FirstRow(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?',
        [$schema, $userTable, 'dingding']
    );
    if ($old !== false) {
        $db->Execute('ALTER TABLE `' . $userTable . '` CHANGE dingding feishu varchar(500) NULL');
        echo "renamed {$userTable}.dingding to feishu\n";
    } else {
        $db->Execute('ALTER TABLE `' . $userTable . '` ADD feishu varchar(500) NULL');
        echo "added {$userTable}.feishu\n";
    }
} else {
    $db->Execute('ALTER TABLE `' . $userTable . '` MODIFY feishu varchar(500) NULL');
}

// The Wooyun coin prize invite category was removed; invites are now a single list.
$inviteTable = Tb('invite_reg');
$col = $db->FirstRow(
    'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?',
    [$schema, $inviteTable, 'isWooyun']
);
if ($col !== false) {
    $db->Execute('ALTER TABLE `' . $inviteTable . '` DROP COLUMN isWooyun');
    echo "dropped {$inviteTable}.isWooyun\n";
}

echo "upgrade complete\n";
