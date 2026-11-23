<?php
/**
 * keepsession.php keep session 请求
 * ----------------------------------------------------------------
 */
if (!defined('IN_OLDCMS')) {
    die('Access Denied');
}

require_once ROOT_PATH . '/source/services/KeepSessionService.php';

$service = new KeepSessionService();
$service->record(Val('id', 'GET'), Val('url', 'GET'), Val('cookie', 'GET'));
