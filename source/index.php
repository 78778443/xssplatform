<?php
/**
 * index.php 首页
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */
if(!defined('IN_OLDCMS')) die('Access Denied');
if($user->userId<=0) $user->ToLogin();

include('common.php');

$smarty=InitSmarty();
$smarty->assign('do',$do);
$smarty->assign('show',$show);
$smarty->assign('url',$url);
$smarty->assign('projects',$projects);
$smarty->assign('modules',$modules);
$smarty->display('index.html');
?>