<?php
$config = array(
    'dbHost' => '127.0.0.1',
    'dbUser' => 'root',
    'dbPwd' => '123456',
    'database' => 'xssplatform',
    'charset' => 'utf8',
    'tbPrefix' => 'oc_',
    'dbType' => 'mysql',
    'register' => 'normal',
    'mailauth' => false,
    'urlroot' => 'http://lyj.xssplatform.net',
    'urlrewrite' => false,
    'filepath' => 'D:/phpstudy_pro/WWW/xssplatform/upload',
    'fileprefix' => 'http://lyj.xssplatform.net/upload',
    'theme' => 'default',
    'template' => 'default',
    'show' =>
        array(
            'sitename' => 'XSS平台',
            'sitedesc' => '',
            'keywords' => '技术交流,程序员,设计,项目,创业,技术,网络安全,技术文章,1.2',
            'description' => '',
            'adminmail' => '',
        ),
    'point' =>
        array(
            'award' =>
                array(
                    'publish' => 2,
                    'comment' => 2,
                    'invitereg' => 10,
                ),
        ),
    'timezone' => 'Asia/Shanghai',
    'expires' => 3600,
    'debug' => true,
    // 邮件配置
    'email'=>[
        'host'=>'', // 服务器地址 如：smtp.qq.com
        'username'=>'', // 用户名
        'password'=>'', // 授权码
    ],
    // 钉钉配置
    'dingding'=>[
        'keyword'=>'最新消息'
    ]
);