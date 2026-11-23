<?php
/**
 * api.php 收包接口
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */
if (!defined('IN_OLDCMS')) {
    die('Access Denied');
}

require_once ROOT_PATH . '/source/services/ReceiveService.php';

$service = new ReceiveService();
$target = $service->receive($_REQUEST, $_SERVER);
if ($target === null) {
    exit();
}
header('Location: ' . $target);
