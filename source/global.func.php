<?php
/**
 * global.func.php 公共方法
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */
if(!defined('IN_OLDCMS')) die('Access Denied');


function UrlInvite($inviteKey){
	return URL_ROOT.(URL_REWRITE ? "/register/{$inviteKey}" : "/index.php?do=register&key={$inviteKey}");
}
?>