<?php
/**
 * index.php front controller: install shell, SPA shell, legacy routes and receivers.
 */
if (version_compare(PHP_VERSION, '8.0', '<')) {
    die('此程序需PHP版本大于8.0 !');
}

define('ROOT_PATH', dirname(__FILE__));
define('IN_OLDCMS', true);

require_once ROOT_PATH . '/source/lib/Config.php';
require_once ROOT_PATH . '/source/lib/FrontendShell.php';

$installed = file_exists(ROOT_PATH . '/install/install.lock');
$do = isset($_GET['do']) && is_string($_GET['do']) ? $_GET['do'] : '';

if (!$installed || $do === '') {
    FrontendShell::render();
    exit;
}

$receivers = ['code', 'api', 'do', 'keepsession'];
if (in_array($do, $receivers, true)) {
    require ROOT_PATH . '/init.php';
    require ROOT_PATH . '/source/' . $do . '.php';
    exit;
}

// Legacy UI routes are handled by LegacyRouter in Task 11.
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Not Found';
