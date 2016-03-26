<?php
/**
 * user.php 用户管理
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */
if(!defined('IN_OLDCMS')) die('Access Denied');

$act=Val('act','GET');

$where='';
switch($act){
	case 'audit':
		$isAudit=Val('isAudit','GET',1);
		$id=Val('id','GET',1);
		$db=DBConnect();
		$tbModule=$db->tbPrefix.'module';
		$db->Execute("UPDATE {$tbModule} SET isAudit='{$isAudit}',managerId='".$user->userId."',managerName='".$user->userName."' WHERE id='{$id}'");
		ShowSuccess('操作成功',URL_ROOT.'/admin/index.php?do=admin_module');
		break;
	default:
		$db=DBConnect();
		$tbModule=$db->tbPrefix.'module';
		$tbUser=$db->tbPrefix.'user';
		$where=" AND isOpen=1";
		include(ROOT_PATH.'/source/class/Pager.class.php');
		$countSql="SELECT COUNT(*) FROM {$tbModule} WHERE 1=1 {$where} ORDER BY id DESC";
		$sql="SELECT m.*,u.userName AS userName FROM {$tbModule} m INNER JOIN {$tbUser} u ON u.id=m.userId WHERE 1=1 {$where} ORDER BY id DESC";
		$href='./index.php?do=admin_module';
		if(!empty($act)) $href.='&act='.$act;
		$pager=new Pager($countSql,$sql,$href,20,5,Val('pNO','GET',1));
		$modules=$pager->data;
		$smarty=InitSmarty(1);
		$smarty->assign('modules',$modules);
		$smarty->assign('nav',$pager->nav);
		$smarty->assign('do',$do);
		$smarty->assign('show',$show);
		$smarty->assign('url',$url);
		$smarty->display('admin_module.html');
		break;
}
?>