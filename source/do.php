<?php
/**
 * api.php 接口
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */
if(!defined('IN_OLDCMS')) die('Access Denied');

$auth=Val('auth','GET');
$db=DBConnect();
$project=$db->FirstRow("SELECT * FROM ".Tb('project')." WHERE authCode='{$auth}'");
if(empty($project)) exit('Auth Err.');

switch($act){
	case 'content':
	default:
		$domain=Val('domain','GET');
		$where='';
		if(!empty($domain)) $where.=" AND domain='{$domain}'";
		$contents=$db->FirstColumn("SELECT content FROM ".Tb('project_content')." WHERE projectId='{$project[id]}' {$where} ORDER BY id DESC");
		$data=array();
		foreach($contents as $k=>$v){
			$row=array();
			$v=(array)json_decode($v);
			$row['url']=$v['opener']?$v['opener']: $v['toplocation'];
			$row['cookie']=$v['cookie'];
			$data[]=$row;
		}
		echo JsonEncode($data);
		break;
}
?>