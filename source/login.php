<?php
/**
 * login.php 登录
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */
if(!defined('IN_OLDCMS')) die('Access Denied');

$act=Val('act','GET');
switch($act){
	case 'submit':
		$username=Val('user','POST');
		if(empty($username)) ShowError('用户/邮箱不能为空',$url['login']);
		$userpwd=Val('pwd','POST');
		if(empty($userpwd)) ShowError('密码不能为空',$url['login']);
		$captcha=Val('captcha','POST');
		/*
		if(empty($captcha)) ShowError('请输入验证码',$url['login']);
		//判断验证码
		include(ROOT_PATH.'/source/class/Captcha.class.php');
		if(!Captcha::Check($captcha)) ShowError('验证码输入错误',$url['login']);
		*/
		$auto=Val('auto','POST',1);
		if($user->userId<=0){
			if($user->Login($username,$userpwd,$auto)){
				ShowSuccess('登录成功');
			}else{
				ShowError('登录失败,请检查用户/邮箱或密码',$url['login']);
			}
		}
		break;
	case 'logout':
		if($user->Logout()){
			ShowSuccess('成功退出');
		}
		break;
	default:
		if($user->userId>0){
			ShowError('已经登录');
		}
		$smarty=InitSmarty();
		$smarty->assign('do',$do);
		$smarty->assign('show',$show);
		$smarty->assign('url',$url);
		$smarty->display('login.html');
		break;
}
?>