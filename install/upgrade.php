<?php
/**
 * Idempotent database upgrade for existing installations.
 * Usage: XSSP_CONFIG=/path/to/config.php php install/upgrade.php
 */
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

echo "upgrade complete\n";
