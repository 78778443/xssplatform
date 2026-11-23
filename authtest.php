<?php
/**
 * authtest.php Basic Auth 钓鱼端点
 * ----------------------------------------------------------------
 */
ini_set('display_errors', '0');
header_remove('X-Powered-By');

define('ROOT_PATH', dirname(__FILE__));
define('IN_OLDCMS', true);

require_once ROOT_PATH . '/source/lib/Config.php';
require_once ROOT_PATH . '/source/lib/Display.php';

$configFile = ROOT_PATH . '/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    exit('Not installed.');
}
Config::load($configFile);
$urlRoot = (string) Config::get('urlroot', '');

$info = str_replace(["\r", "\n"], '', (string) ($_GET['info'] ?? ''));
$id = (string) ($_GET['id'] ?? '');

if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
    header('WWW-Authenticate: Basic realm="' . addslashes($info) . '"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Authorization Required.';
    exit;
}

$params = [
    'do' => 'api',
    'id' => $id,
    'username' => (string) $_SERVER['PHP_AUTH_USER'],
    'password' => (string) $_SERVER['PHP_AUTH_PW'],
];
header('Location: ' . $urlRoot . '/index.php?' . http_build_query($params));
