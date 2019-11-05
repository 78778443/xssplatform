<?php

if (file_exists("./install/install.lock")) {
    header("location:/index.php");
    exit("已安装，程序不再往下执行");
}
$project = 'Permeate渗透测试系统 XSS管理平台';