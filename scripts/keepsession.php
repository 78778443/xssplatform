<?php
/**
 * keepsession.php keep session执行
 * ----------------------------------------------------------------
 */
include('../init.php');

$db=DBConnect();
$rows=$db->Dataset("SELECT * FROM ".Tb('keepsession'));

$f=new SaeFetchurl();

foreach($rows as $row){
	if(time()>$row['updateTime']+300){
		$f->setHeader("Cookie",urldecode($row['cookie']));
		$con=$f->fetch($row['url']);
		$db->Execute("UPDATE ".Tb('keepsession')." SET updateTime='".time()."' WHERE id='{$row[id]}'");
	}
}
?>