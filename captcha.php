<?php
/**
 * captcha.php 输出验证码
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */

include('init.php');
include(ROOT_PATH.'/source/class/Captcha.class.php');
//输入验证码
Captcha::Create();
?>