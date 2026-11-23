<?php
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}
if (!defined('IN_OLDCMS')) {
    define('IN_OLDCMS', true);
}

if (PHP_SAPI !== 'cli') {
    // Never print stack traces or framework paths to visitors; use the error log.
    ini_set('display_errors', '0');
    header_remove('X-Powered-By');
}

require_once ROOT_PATH . '/source/lib/Config.php';
require_once ROOT_PATH . '/source/lib/Security.php';
require_once ROOT_PATH . '/source/lib/Display.php';
require_once ROOT_PATH . '/source/lib/Validate.php';
require_once ROOT_PATH . '/source/lib/Notify.php';
require_once ROOT_PATH . '/source/lib/RateLimiter.php';

Config::load(getenv('XSSP_CONFIG') ?: ROOT_PATH . '/config.php');

require_once ROOT_PATH . '/source/function.php';
require_once ROOT_PATH . '/source/class/DB.class.php';
require_once ROOT_PATH . '/source/class/User.class.php';

date_default_timezone_set((string) Config::get('timezone', 'Asia/Shanghai'));

if (!defined('URL_ROOT')) {
    define('URL_ROOT', (string) Config::get('urlroot', ''));
}
if (!defined('URL_REWRITE')) {
    define('URL_REWRITE', (bool) Config::get('urlrewrite', false));
}
if (!defined('REGISTER')) {
    define('REGISTER', (string) Config::get('register', 'normal'));
}
if (!defined('EXPIRES')) {
    define('EXPIRES', (int) Config::get('expires', 3600));
}
if (!defined('TABLE_PREFIX')) {
    define('TABLE_PREFIX', (string) Config::get('tbPrefix', 'oc_'));
}

$show = Config::get('show', []);
$db = DBConnect();
$user = new User($db);

// bootstrap may be included from a method scope (e.g. Api::handle); publish the
// request context as real globals so db()/current_user() and legacy endpoints see it.
$GLOBALS['show'] = $show;
$GLOBALS['db'] = $db;
$GLOBALS['user'] = $user;

/**
 * Shared database connection for this request.
 */
function db(): DB_Mysql
{
    global $db;
    return $db;
}

/**
 * Authenticated user for this request.
 */
function current_user(): User
{
    global $user;
    return $user;
}
