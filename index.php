<?php
/**
 * index.php front controller: install shell, SPA shell, legacy routes and receivers.
 */
if (version_compare(PHP_VERSION, '8.0', '<')) {
    die('此程序需PHP版本大于8.0 !');
}

ini_set('display_errors', '0');
header_remove('X-Powered-By');

define('ROOT_PATH', dirname(__FILE__));
define('IN_OLDCMS', true);

require_once ROOT_PATH . '/source/lib/Config.php';
require_once ROOT_PATH . '/source/lib/FrontendShell.php';

$installed = file_exists(ROOT_PATH . '/install/install.lock');
$do = isset($_GET['do']) && is_string($_GET['do']) ? $_GET['do'] : '';

if (!$installed) {
    // Fresh deployments go straight to the installer; the SPA cannot work yet.
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if ($path !== '/install') {
        header('Location: /install');
        exit;
    }
    FrontendShell::render();
    exit;
}

if ($do === '') {
    FrontendShell::render();
    exit;
}

$receivers = ['code', 'api', 'do', 'keepsession'];
if (in_array($do, $receivers, true)) {
    require ROOT_PATH . '/init.php';
    require ROOT_PATH . '/source/' . $do . '.php';
    exit;
}

require ROOT_PATH . '/init.php';
require_once ROOT_PATH . '/source/lib/AppError.php';
require_once ROOT_PATH . '/source/lib/UrlMap.php';
require_once ROOT_PATH . '/source/LegacyRouter.php';

LegacyRouter::route($do, $_GET, $_POST, $_SERVER);
