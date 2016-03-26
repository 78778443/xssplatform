<?php
/**
 * Captcha.class.php 验证码操作类
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */
if(!defined('IN_OLDCMS')) die('Access Denied');

class Captcha{
	/* 生成验证码 */
	public static function Create($width=75,$height=20,$num=4){
		/* 获得验证码 */
		$captcha='';
		$str='abcdefghjkmnpqrstuvwxyz';
		$str.=strtoupper($str);
		$str.='23456789';
		$len=strlen($str);
		for($i=0;$i<$num;$i++){
			$key=rand(0,$len-1);
			$captcha.=$str[$key];
		}
		/* 生成验证码图片 */
		$im=imagecreate($width,$height);
		//主要用到黑白灰三种色
		$black=imagecolorallocate($im,0,0,0);
		$white=imagecolorallocate($im,255,255,255);
		$gray=imagecolorallocate($im,200,200,200);
		imagefill($im,$width,$height,$black);
		//干扰线
		$li=imagecolorallocate($im,220,220,220);
		for($i=0;$i<3;$i++){ 
			imageline($im,rand(0,30),rand(0,21),rand(20,40),rand(0,21),$li);
		}
		//插入验证码字符
		imagestring($im,5,15,2,$captcha,$white);
		//加入干扰象素
		for($i=0;$i<$width;$i++){
			imagesetpixel($im,rand()%80,rand()%30,$gray);
		}
		/* 将验证码存储至session */
		@session_start();
		$_SESSION['captcha']=$captcha;
		/* 输出 */
		header("Content-type:image/png");
		imagepng($im);
		imagedestroy($im);
	}
	/* 验证 */
	public static function Check($captcha=''){
		@session_start();
		$captchaData=strtolower($_SESSION['captcha']);
		$_SESSION['captcha']='';
		unset($_SESSION['captcha']);
		return strtolower($captcha)==$captchaData;
	}
}