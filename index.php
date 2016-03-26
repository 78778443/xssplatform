<?php
/**
 * index.php 功能调用页
 * ----------------------------------------------------------------
 * by Gosuto
 */

include('init.php');
$do=Val('do','GET',0);
$dos=array('index','login','project','module','code','api','do','register','user','keepsession');
if(!in_array($do,$dos)) $do='index';
include(ROOT_PATH.'/source/'.$do.'.php');


?>