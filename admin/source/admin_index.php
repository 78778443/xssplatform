<?php
/**
 * index.php 后台首页
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */
if(!defined('IN_OLDCMS')) die('Access Denied');

$db=DBConnect();
$tbUser=$db->tbPrefix.'user';
$tbSession=$db->tbPrefix.'session';
$tbContent=$db->tbPrefix.'content';
$tbComment=$db->tbPrefix.'comment';
//社区概况
$situation=array();
$situation['userCount']=$db->FirstValue("SELECT COUNT(*) FROM {$tbUser}"); //注册用户总数
$tbSession=$db->tbPrefix.'session';
$situation['onlineCount']=$db->FirstValue("SELECT COUNT(DISTINCT userId) FROM {$tbSession} WHERE updateTime>".(time()-EXPIRES)); //在线用户总数
$situation['onlineUsers']=$db->Dataset("SELECT DISTINCT u.id,u.userName FROM {$tbSession} s INNER JOIN {$tbUser} u ON u.id=s.userId WHERE updateTime>".(time()-EXPIRES)); //在线用户

//系统环境
$sysInfo=array();
$sysInfo['sys_version']='IT121 1.0';
$sysInfo['serverOS']=PHP_OS;
$sysInfo['serverSoftware']=$_SERVER['SERVER_SOFTWARE'];
$sysInfo['phpVersion']='PHP v'.PHP_VERSION;
$sysInfo['mysqlVersion']='MySQL '.$db->FirstValue('SELECT VERSION()');

include(ROOT_PATH.'/source/common.php');
$smarty=InitSmarty(1);

$smarty->assign('situation',$situation);
$smarty->assign('sysInfo',$sysInfo);
$smarty->assign('do',$do);
$smarty->assign('show',$show);
$smarty->assign('url',$url);
$smarty->display('admin_index.html');
?>