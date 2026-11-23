<?php
/**
 * do.php 认证数据接口
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */
if (!defined('IN_OLDCMS')) {
    die('Access Denied');
}

require_once ROOT_PATH . '/source/services/AuthApiService.php';

$auth = Val('auth', 'GET');
$project = db()->FirstRow('SELECT id FROM ' . Tb('project') . ' WHERE authCode=?', [$auth]);
if (empty($project)) {
    exit('Auth Err.');
}

$service = new AuthApiService();
echo JsonEncode($service->data($auth, Val('domain', 'GET')));
