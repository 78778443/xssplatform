<?php
/**
 * code.php 代码文件
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */
if(!defined('IN_OLDCMS')) die('Access Denied');
//输入文件格式为js
header("Content-type: application/x-javascript");
header("Cache-Control: nocache");
header("Pragma: no-cache");

$id=Val('id','GET',1);
$urlKey=Val('urlKey','GET');
$db=DBConnect();
$project=$db->FirstRow("SELECT * FROM ".Tb('project')." WHERE id='{$id}' OR urlKey='{$urlKey}'");
if(empty($project)) exit();
$moduleSetKeys=json_decode($project['moduleSetKeys'],true);
/* 模块 begin */
$moduleIds=array();
if(!empty($project['modules'])) $moduleIds=json_decode($project['modules']);
if(!empty($moduleIds)){
	$modulesStr=implode(',',$moduleIds);
	$modules=$db->Dataset("SELECT * FROM ".Tb('module')." WHERE id IN ($modulesStr)");
	if(!empty($modules)){
		foreach($modules as $module){
			$module['code']=str_replace('{projectId}',$project['urlKey'],$module['code']);
			//module里是否有配置的参数
			if(!empty($module['setkeys'])){
				$setkeys=json_decode($module['setkeys'],true);
				foreach($setkeys as $setkey){
					$module['code']=str_replace('{set.'.$setkey.'}',$moduleSetKeys["setkey_{$module[id]}_{$setkey}"],$module['code']);
				}
			}
			echo htmlspecialchars_decode($module['code'],ENT_QUOTES);
		}	
	}
}
/* 模块 end */
/* 项目自定义代码 */
echo htmlspecialchars_decode($project['code'],ENT_QUOTES);
?>