<?php
/**
 * register.php 注册
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */
if(!defined('IN_OLDCMS')) die('Access Denied');
if(REGISTER=='close') ShowError('注册功能已关闭');

$act=Val('act','GET');
switch($act){
	case 'checkue':
		$username=Val('user','POST');
		$email=Val('email','POST');
		$key=Val('key','POST');
		$db=DBConnect();
		$tbUser=$db->tbPrefix.'user';
		$userExisted=$db->FirstValue("SELECT COUNT(*) FROM {$tbUser} WHERE userName='{$username}'");
		$emailExisted=$db->FirstValue("SELECT COUNT(*) FROM {$tbUser} WHERE email='{$email}'");
		$keyError=0;
		if(REGISTER=='invite'){
			//判断key是否有效
			$tbInviteReg=$db->tbPrefix.'invite_reg';
			$inviteRow=$db->FirstRow("SELECT id,userId FROM {$tbInviteReg} WHERE inviteKey='{$key}' AND isUsed=0");
			if(empty($inviteRow)) $keyError=1;
		}

		echo $userExisted.'|'.$emailExisted.'|'.$keyError;
		break;
	case 'submit':
		if($user->userId>0) ShowError('您已登录,不能进行注册');
		$db=DBConnect();
		$key=Val('key','POST');
		if(!empty($key)){
			$tbInviteReg=$db->tbPrefix.'invite_reg';
			$inviteRow=$db->FirstRow("SELECT id,userId FROM {$tbInviteReg} WHERE inviteKey='{$key}' AND isUsed=0");
		}
		if(REGISTER=='invite'){
			if(empty($key)) ShowError('本站目前仅能邀请注册');
			if(empty($inviteRow)) ShowError('你的邀请码不正确或已作废');
		}
		$username=Val('user','POST');
		$email=Val('email','POST');
		$userpwd=Val('pwd','POST');
		$phone=Val('phone','POST');//获取手机号
		//判断格式
		if(empty($username) || !preg_match('/^[\w\x{4e00}-\x{9fa5}]{2,20}$/u',$username)) ShowError('用户格式不正确',$url['register'],'重新填写');
		if(empty($email) || !preg_match('/^(\w+\.)*?\w+@(\w+\.)+\w+$/',$email)) ShowError('邮箱格式不正确',$url['register'],'重新填写');
		if(!empty($phone) && !preg_match('/^(\d{11})$/',$phone)) ShowError('手机格式不正确',$url['register'],'重新填写');//手机验证
		if(empty($userpwd) || !preg_match('/^.{6,20}$/',$userpwd)) ShowError('密码应为6-20位字符',$url['register'],'重新填写');
		$tbUser=$db->tbPrefix.'user';
		//用户是否存在
		$userExisted=$db->FirstValue("SELECT COUNT(*) FROM {$tbUser} WHERE userName='{$username}'");
		if($userExisted>0) ShowError("用户{$username}已存在",$url['register'],'重新填写');
		//邮箱是否存在
		$emailExisted=$db->FirstValue("SELECT COUNT(*) FROM {$tbUser} WHERE email='{$email}'");
		if($emailExisted>0) ShowError("邮箱{$email}已存在",$url['register'],'重新填写');
		//入库
		$executeArr=array('userName'=>$username,'userPwd'=>OCEncrypt($userpwd),'email'=>$email,'phone'=>$phone,'addTime'=>time());
		if($db->AutoExecute($tbUser,$executeArr)){
			if(!empty($inviteRow)){
				$regUserId=$db->LastId();
				$db->Execute("UPDATE {$tbInviteReg} SET isUsed=1,regUserId='{$regUserId}',regTime='".time()."' WHERE id='{$inviteRow[id]}'");
			}
			//自动登录
			$user->Login($username,$userpwd,1);
			ShowSuccess('注册成功',$url['root']);
		}else{
			ShowError('出错了,请与管理员联系');
		}
		break;
	default:
		if($user->userId>0) ShowError('您已登录,不能进行注册!');
		$key=Val('key','GET');
		$smarty=InitSmarty();
		$smarty->assign('do',$do);
		$smarty->assign('register',REGISTER);
		$smarty->assign('key',$key);
		$smarty->assign('show',$show);
		$smarty->assign('url',$url);
		$smarty->display('register.html');
		break;
}
?>