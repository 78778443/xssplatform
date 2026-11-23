<?php
require __DIR__ . '/../lib.php';

$tmp = '/tmp/xssp-install-test';
exec('rm -rf ' . escapeshellarg($tmp));
exec('rsync -a --exclude .git --exclude frontend/node_modules --exclude .superpowers '
    . escapeshellarg(dirname(__DIR__, 2)) . '/ ' . escapeshellarg($tmp) . '/');
@unlink($tmp . '/config.php');
@unlink($tmp . '/install/install.lock');

$cfg = include __DIR__ . '/../config.test.php';
$host = $cfg['dbHost'];
$port = 3306;
if (str_contains($host, ':')) {
    [$host, $port] = explode(':', $host, 2);
}
$cleanup = new mysqli($host, $cfg['dbUser'], $cfg['dbPwd'], null, (int) $port);
$cleanup->query('DROP DATABASE IF EXISTS xssplatform_install_test');
$cleanup->close();

putenv('XSSP_CONFIG=' . $tmp . '/config.php');
define('ROOT_PATH', $tmp);
define('IN_OLDCMS', true);
require $tmp . '/source/lib/Config.php';
require $tmp . '/source/lib/AppError.php';
require $tmp . '/source/lib/ConfigWriter.php';
require $tmp . '/source/class/DB.class.php';
require $tmp . '/source/services/InstallService.php';

test('installer creates config, database, admin hash and lock', function () use ($tmp, $cfg) {
    $svc = new InstallService();
    assertFalse($svc->status()['installed']);
    assertTrue($svc->status()['configWritable'], 'config writable');

    assertThrows(AppError::class, function () use ($svc, $cfg) {
        $svc->install([
            'dbHost' => $cfg['dbHost'],
            'dbUser' => $cfg['dbUser'],
            'dbPwd' => $cfg['dbPwd'],
            'dbName' => 'xssplatform_install_test',
            'username' => 'a<b',
            'password' => 'pw123456',
            'urlroot' => 'http://127.0.0.1:18081',
        ]);
    });

    $svc->install([
        'dbHost' => $cfg['dbHost'],
        'dbUser' => $cfg['dbUser'],
        'dbPwd' => $cfg['dbPwd'],
        'dbName' => 'xssplatform_install_test',
        'username' => 'admin2',
        'password' => 'p&ssw0rd',
        'urlroot' => 'https://xss.example.com:8443',
    ]);
    assertTrue(file_exists($tmp . '/install/install.lock'), 'lock written');
    assertTrue(file_exists($tmp . '/config.php'), 'config written');

    $db = new DB_Mysql();
    assertTrue($db->Connect($cfg['dbHost'], $cfg['dbUser'], $cfg['dbPwd'], 'xssplatform_install_test', 'utf8', 'oc_'), 'connect installed db');
    assertSame('admin2', $db->FirstValue('SELECT userName FROM oc_user ORDER BY id LIMIT 1'));
    // Login applies StripStr before verifying, so the installer must hash the same transform.
    $stored = (string) $db->FirstValue('SELECT userPwd FROM oc_user ORDER BY id LIMIT 1');
    assertTrue(password_verify(addslashes(htmlspecialchars('p&ssw0rd', ENT_QUOTES)), $stored), 'stripped password verifies');
    assertTrue(!password_verify('p&ssw0rd', $stored), 'raw password does not verify');
    assertSame(1, (int) $db->FirstValue('SELECT adminLevel FROM oc_user ORDER BY id LIMIT 1'));

    // Seed module payloads must point at the configured root (scheme, host, port).
    $moduleCode = (string) $db->FirstValue('SELECT code FROM oc_module ORDER BY id LIMIT 1');
    assertTrue(str_contains($moduleCode, 'https://xss.example.com:8443/'), 'module url uses full urlroot');
    assertTrue(!str_contains($moduleCode, 'xsser.me'), 'legacy demo origin replaced');

    assertThrows(AppError::class, function () use ($svc, $cfg) {
        $svc->install([
            'dbHost' => $cfg['dbHost'],
            'dbUser' => $cfg['dbUser'],
            'dbPwd' => $cfg['dbPwd'],
            'dbName' => 'xssplatform_install_test',
            'username' => 'admin2',
            'password' => 'pw123456',
        ]);
    });
});
