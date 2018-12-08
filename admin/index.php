<?php
/**
 * index.php admin后台默认页
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */
require '../init.php';
if ($user->adminLevel <= 0) {
    die('Access Denied');
}

define('ADMIN_PATH', dirname(__FILE__));
!defined('TEMPLATE_PATH') && define('TEMPLATE_PATH', dirname(__FILE__));
$do = Val('do', 'GET', 0);
$dos = array('admin_index', 'admin_module');

if (!in_array($do, $dos)) {
    $do = 'admin_index';
}
require ADMIN_PATH . '/source/' . $do . '.php';
?>
