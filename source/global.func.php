<?php
/**
 * global.func.php 公共方法
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */
if (!defined('IN_OLDCMS')) {
    die('Access Denied');
}


function UrlInvite($inviteKey)
{
    return URL_ROOT . (URL_REWRITE ? "/register/{$inviteKey}" : "/index.php?do=register&key={$inviteKey}");
}


/**
 * 钉钉通知
 *
 * @param  $message
 * @param  string $remote_server
 * @return array|mixed
 */
function dingdingNotice($message, $key)
{
    $config = [];
    require ROOT_PATH . '/config.php';
    $remote_server =  "https://oapi.dingtalk.com/robot/send?access_token={$key}";
    $data = array('msgtype' => 'text', 'text' => array('content' => "{$config['dingding']['keyword']}：".$message));
    $post_string = json_encode($data);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $remote_server);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json;charset=utf-8'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
    //curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //不验证证书
    $data = curl_exec($ch);
    if(curl_error($ch)){
        return curl_error($ch);
    }
    curl_close($ch);

    return $data;
}