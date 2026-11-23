<?php
/**
 * code.php 代码文件
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */
if (!defined('IN_OLDCMS')) {
    die('Access Denied');
}
// 输出文件格式为 js
header("Content-type: application/x-javascript");
header("Cache-Control: nocache");
header("Pragma: no-cache");

require_once ROOT_PATH . '/source/services/CodeService.php';

$service = new CodeService();
echo $service->render(Val('id', 'GET', 1), Val('urlKey', 'GET'));
