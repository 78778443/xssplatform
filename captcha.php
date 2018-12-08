<?php
/**
 * captcha.php 输出验证码
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */

require 'init.php';
require ROOT_PATH.'/source/class/Captcha.class.php';
//输入验证码
Captcha::Create();
?>
