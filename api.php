<?php
/**
 * api.php JSON API front controller.
 * Usage: /api.php?action=<name>
 */
define('ROOT_PATH', dirname(__FILE__));
define('IN_OLDCMS', true);

require_once ROOT_PATH . '/source/lib/AppError.php';
require_once ROOT_PATH . '/source/lib/Config.php';
require_once ROOT_PATH . '/source/lib/Api.php';

Api::handle(
    isset($_GET['action']) && is_string($_GET['action']) ? $_GET['action'] : '',
    [],
    $_SERVER,
    $_GET,
    $_POST,
    $_COOKIE
);
