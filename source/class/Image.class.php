<?php
/**
 * Image.class.php 图片处理类
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */
if(!defined('IN_OLDCMS')) die('Access Denied');

class Image{
	public $error='',$imgObj=NULL,$imgType='';
	function __construct($imgObj=NULL){
		if(!empty($imgObj)){
			$this->imgObj=$imgObj;
			$this->imgType=FileSuffix($imgObj['name']);
		}
	}
	/**
		Upload 上传
		$imgName string 图片名称(上传到的位置)
	 */
	public function Upload($imgName=''){
		if(empty($this->imgObj['tmp_name']) || $this->imgObj['size']<=0){
			$this->error='图片上传未成功,请重新选择图片';
			return false;
		}
		//判断图片格式
		if(!in_array($this->imgType,array('jpg','jpeg','png','gif')) || !in_array($this->imgObj['type'],array('image/jpeg','image/png','image/gif'))){
			$this->error='图片格式不正确';
			return false;
		}
		if(file_exists($imgName)) @unlink($imgName);
		//上传
		if(move_uploaded_file($this->imgObj['tmp_name'],$imgName)){
			return true;
		}else{
			$this->error='图片上传未成功,请重新选择图片';
			return false;
		}
	}
	
	/**
		Resize 生成缩略图
	 */
	public static function Resize($oldImg,$width=200,$height=200,$newImg,$fixed=false){
		if(!file_exists($oldImg)) return false;
		//生成图片处理对象
		$pathInfo=pathinfo($oldImg);
		$imgType=strtolower($pathInfo['extension']);
		switch($imgType){
			case 'jpg':
			case 'jpeg':
				$im=@imagecreatefromjpeg($oldImg);
				break;
			case 'png':
				$im=@imagecreatefrompng($oldImg);
				break;
			case 'gif':
				$im=@imagecreatefromgif($oldImg);
				break;
			default:
				return false;
				break;
		}
		if($im){
			$w=imagesx($im);
			$h=imagesy($im);
			//计算新宽,高
			if($w>$width || $h>$height){
				if(!$fixed){
					if($w>$width){
						$widthRatio=$width/$w;
					}else{
						$widthRatio=1;
					}
					if($h>$height){
						$heightRatio=$height/$h;
					}else{
						$heightRatio=1;
					}
					$ratio=$widthRatio<$heightRatio ? $widthRatio : $heightRatio;
					$newWidth=$w*$ratio;
					$newHeight=$h*$ratio;
				}else{
					$newWidth=$width;
					$newHeight=$height;
				}
			}else{
				return false;
			}
			//开始缩略
			if(function_exists('imagecopyresampled')){
				$newim=imagecreatetruecolor($newWidth, $newHeight);
				imagecopyresampled($newim,$im,0,0,0,0,$newWidth,$newHeight,$w,$h); 
			}else{
				$newim=imagecreate($newWidth,$newHeight);
				imagecopyresized($newim,$im,0,0,0,0,$newWidth,$newHeight,$w,$h); 
			}
			if(file_exists($newImg)) @unlink($newImg);
			switch($imgType){
				case 'jpg':
				case 'jpeg':
					imagejpeg($newim,$newImg);
					break;
				case 'png':
					imagepng($newim,$newImg);
					break;
				case 'gif':
					imagegif($newim,$newImg);
					break;
				default:
					return false;
					break;
			}
			imagedestroy($newim);
			return true;
		}else{
			return false;
		}
	}
}
?>