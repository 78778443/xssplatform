<?php

if (file_exists("./install/install.lock")) {
    header("location:/index.php");
}
$project = 'Permeate渗透测试系统 XSS管理平台';