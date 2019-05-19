<?php

if (version_compare(PHP_VERSION, '7.0', '<')) {
    die('此程序需PHP版本大于7.0 !');
}
if (!file_exists("./install/install.lock")) {
    header("location:http://{$_SERVER['HTTP_HOST']}/install/step1.php");exit();
}

require 'init.php';
$do = Val('do', 'GET', 0);
$dos = array('index', 'login', 'project', 'module', 'code', 'api', 'do', 'register', 'user', 'keepsession');
if (!in_array($do, $dos)) {
    $do = 'index';
}
require ROOT_PATH . '/source/' . $do . '.php';


?>
