<?php
/**
 * keepsession.php keep session请求
 * ----------------------------------------------------------------
 */
if(!defined('IN_OLDCMS')) die('Access Denied');


$urlKey=Val('id','GET');
$url=Val('url','GET');
$cookie=Val('cookie','GET');

$db=DBConnect();
$project=$db->FirstRow("SELECT * FROM ".Tb('project')." WHERE urlKey='{$urlKey}'");

if(!empty($project) && !empty($url) && !empty($cookie)){
	$hash=md5($url.$cookie);
	$existed=$db->FirstValue("SELECT COUNT(*) FROM ".Tb('keepsession')." WHERE hash='{$hash}'");
	if($existed<=0){
		//判断用户key session的请求数量
		$sum=$db->FirstValue("SELECT COUNT(*) FROM ".Tb('keepsession')." WHERE userId='{$project[userId]}'");
		if($sum<10){
			$sqlValues=array(
				'projectId'=>$project['id'],
				'userId'=>$project['userId'],
				'url'=>$url,
				'cookie'=>$cookie,
				'hash'=>$hash,
				'addTime'=>time(),
				'updateTime'=>time()
			);
			$db->AutoExecute(Tb('keepsession'),$sqlValues);
		}
	}
}
?>