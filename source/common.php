<?php
/**
 * common.php 公共文件
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */
if(!defined('IN_OLDCMS')) die('Access Denied');

if(empty($db)) $db=DBConnect();
//项目
$projects=$db->Dataset("SELECT * FROM ".Tb('project')." WHERE userId='".$user->userId."' ORDER BY id dESC");
foreach($projects as $k=>$v){
	$projects[$k]['contentNum']=$db->FirstValue("SELECT COUNT(*) FROM ".Tb('project_content')." WHERE projectId='{$v[id]}'");
}
//模块
$modules=$db->Dataset("SELECT * FROM ".Tb('module')." WHERE userId='".$user->userId."' OR (isOpen=1 AND isAudit=1) ORDER BY id dESC");
?>