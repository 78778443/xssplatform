<?php
/**
 * Point the installed test copy at local MailHog and the mock Feishu endpoint.
 */
$configFile = '/tmp/xssp-http-installed/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "installed test config not found\n");
    exit(1);
}
$config = [];
include $configFile;
$config['email']['host'] = '127.0.0.1';
$config['email']['port'] = 1025;
$config['email']['secure'] = '';
$config['email']['auth'] = false;
$config['email']['username'] = 'test@example.com';
$config['email']['password'] = '';
$config['feishu']['endpoint'] = 'http://127.0.0.1:19090/send';
file_put_contents($configFile, "<?php\n\$config = " . var_export($config, true) . ";\n");
echo "notify config patched\n";
