<?php 
$config = array (
  'dbHost' => '127.0.0.1:3309',
  'dbUser' => 'root',
  'dbPwd' => '123',
  'database' => 'xssplatform',
  'charset' => 'utf8',
  'tbPrefix' => 'oc_',
  'dbType' => 'mysql',
  'register' => 'normal',
  'mailauth' => false,
  'urlroot' => 'http://xss.localhost',
  'urlrewrite' => false,
  'filepath' => '/Users/song/mycode/safe/privatexss/upload',
  'fileprefix' => 'http://xss.localhost/upload',
  'theme' => 'default',
  'template' => 'default',
  'show' => 
  array (
    'sitename' => 'XSS平台',
    'sitedesc' => '',
    'keywords' => '技术交流,程序员,设计,项目,创业,技术,网络安全,技术文章,1.2',
    'description' => '',
    'adminmail' => '',
  ),
  'point' => 
  array (
    'award' => 
    array (
      'publish' => 2,
      'comment' => 2,
      'invitereg' => 10,
    ),
  ),
  'timezone' => 'Asia/Shanghai',
  'expires' => 3600,
  'debug' => true,
);