<?php
/**
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */
if(!defined('IN_OLDCMS')) die('Access Denied');
if($user->userId<=0) $user->ToLogin();

$act=Val('act','GET');
switch($act){
	case 'create':
		$db=DBConnect();
		//$keys=$db->Dataset("SELECT * FROM ".Tb('config')." WHERE typeId=1");

		include('common.php');
		$smarty=InitSmarty();
		$smarty->assign('do',$do);
		$smarty->assign('show',$show);
		$smarty->assign('url',$url);
		$smarty->assign('keys',$keys);
		$smarty->assign('projects',$projects);
		$smarty->assign('modules',$modules);
		$smarty->display('module_create.html');
		break;
	case 'create_submit':
		if(! $user->CheckToken(Val('token','POST'))) ShowError('操作失败');
		$title=Val('title','POST');
		$description=Val('description','POST');
		if(empty($title)) ShowError('模块名称不能为空',URL_ROOT.'/index.php?do=module&act=create');
		$keys=Val('keys','POST',0,1);
		$keys=JsonEncode($keys);
		$setkeys=Val('setkeys','POST',0,1);
		$setkeys=JsonEncode($setkeys);
		$isOpen=Val('isOpen','POST',1);
		$code=Val('code','POST');
		$values=array(
			'title'=>$title,
			'description'=>$description,
			'userId'=>$user->userId,
			'`keys`'=>$keys,
			'`setkeys`'=>$setkeys,
			'code'=>$code,
			'isOpen'=>$isOpen,
			'addTime'=>time()
		);
		$db=DBConnect();
		$db->AutoExecute(Tb('module'),$values);
		ShowSuccess('创建成功');
		break;
	case 'view':
		$id=Val('id','GET',1);
		$db=DBConnect();
		//读取module信息
		if($user->adminLevel<=0){
			$moduleExisted=$db->FirstValue("SELECT COUNT(*) FROM ".Tb('module')." WHERE id='{$id}' AND (userId='".$user->userId."' OR (isOpen=1 AND isAudit=1))");
			if($moduleExisted<=0) ShowError('模块不存在或没有权限');
		}
		$module=$db->FirstRow("SELECT * FROM ".Tb('module')." WHERE id='{$id}'");
		$keys=array();
		if(!empty($module['keys'])) $keys=json_decode($module['keys']);
		$setkeys=array();
		if(!empty($module['setkeys'])) $setkeys=json_decode($module['setkeys']);

		include('common.php');
		$smarty=InitSmarty();
		$smarty->assign('do',$do);
		$smarty->assign('show',$show);
		$smarty->assign('url',$url);
		$smarty->assign('module',$module);
		$smarty->assign('projects',$projects);
		$smarty->assign('modules',$modules);
		$smarty->assign('keys',$keys);
		$smarty->assign('setkeys',$setkeys);
		$smarty->display('module_view.html');
		break;
	case 'set':
		$id=Val('id','GET',1);
		$db=DBConnect();
		//读取module信息
		$module=$db->FirstRow("SELECT * FROM ".Tb('module')." WHERE id='{$id}' AND userId='".$user->userId."'");
		if(empty($module)) ShowError('模块不存在或没有权限');
		if($user->adminLevel<=0){
			if($module['isOpen']==1 && $module['isAudit']==1)  ShowError('已通过审核的公共模块不能修改');
		}
		$keys=array();
		if(!empty($module['keys'])) $keys=json_decode($module['keys']);
		$setkeys=array();
		if(!empty($module['setkeys'])) $setkeys=json_decode($module['setkeys']);

		include('common.php');
		$smarty=InitSmarty();
		$smarty->assign('do',$do);
		$smarty->assign('show',$show);
		$smarty->assign('url',$url);
		$smarty->assign('module',$module);
		$smarty->assign('projects',$projects);
		$smarty->assign('modules',$modules);
		$smarty->assign('keys',$keys);
		$smarty->assign('setkeys',$setkeys);
		$smarty->display('module_set.html');
		break;
	case 'set_submit':
		if(! $user->CheckToken(Val('token','POST'))) ShowError('操作失败');
		$id=Val('id','POST',1);
		$db=DBConnect();
		//读取module信息
		$module=$db->FirstRow("SELECT * FROM ".Tb('module')." WHERE id='{$id}' AND userId='".$user->userId."'");
		if(empty($module)) ShowError('模块不存在或没有权限');
		if($user->adminLevel<=0){
			if($module['isOpen']==1 && $module['isAudit']==1)  ShowError('已通过审核的公共模块不能修改');
		}
		$title=Val('title','POST');
		$description=Val('description','POST');
		if(empty($title)) ShowError('模块名称不能为空',URL_ROOT.'/index.php?do=module&act=create');
		$keys=Val('keys','POST',0,1);
		$keys=JsonEncode($keys);
		$setkeys=Val('setkeys','POST',0,1);
		$setkeys=JsonEncode($setkeys);
		$isOpen=Val('isOpen','POST',1);
		$code=Val('code','POST');
		$values=array(
			'title'=>$title,
			'description'=>$description,
			'userId'=>$user->userId,
			'`keys`'=>$keys,
			'`setkeys`'=>$setkeys,
			'code'=>$code,
			'isOpen'=>$isOpen
		);
		$db->AutoExecute(Tb('module'),$values,'UPDATE'," id={$id}");
		ShowSuccess('操作成功');
		break;
	case 'delete':
		if(! $user->CheckToken(Val('token','GET'))) ShowError('操作失败');
		$id=Val('id','GET',1);
		$db=DBConnect();
		//读取module信息
		$module=$db->FirstRow("SELECT * FROM ".Tb('module')." WHERE id='{$id}' AND userId='".$user->userId."'");
		if(empty($module)) ShowError('模块不存在或没有权限');
		$db->Execute("DELETE FROM ".Tb('module')." WHERE id='{$id}'");
		ShowSuccess('操作成功');
		break;
	case 'list':
	default:
		include('common.php');

		$smarty=InitSmarty();
		$smarty->assign('do',$do);
		$smarty->assign('show',$show);
		$smarty->assign('url',$url);
		$smarty->assign('projects',$projects);
		$smarty->assign('modules',$modules);
		$smarty->display('module.html');
		break;
}
?>