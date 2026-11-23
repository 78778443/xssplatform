<?php
/**
 * init.php 初始化信息
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */
define('ROOT_PATH', dirname(__FILE__));
define('IN_OLDCMS', true);

require ROOT_PATH . '/source/bootstrap.php';

$show = Config::get('show', []);

// 兼容旧端点/旧模板可能引用的 URL 数组
$url = [];
$url['root'] = Config::get('urlroot', '');
$url['rewrite'] = (bool) Config::get('urlrewrite', false);
$url['login'] = $url['rewrite'] ? $url['root'] . '/login' : $url['root'] . '/index.php?do=login';
$url['register'] = $url['rewrite'] ? $url['root'] . '/register' : $url['root'] . '/index.php?do=register';
