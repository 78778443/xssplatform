<?php
/**
 * admin/index.php 管理员入口（老地址兼容）
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */
define('ROOT_PATH', dirname(__DIR__));
define('IN_OLDCMS', true);

require ROOT_PATH . '/init.php';
require_once ROOT_PATH . '/source/lib/AppError.php';
require_once ROOT_PATH . '/source/lib/UrlMap.php';

$do = Val('do', 'GET', 0);
$act = strtolower((string) Val('act', 'GET'));

if ($act === 'audit' && $do === 'admin_module') {
    require_once ROOT_PATH . '/source/LegacyRouter.php';
    require_once ROOT_PATH . '/source/services/AdminService.php';
    try {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            throw new AppError('method_not_allowed', 405, '请使用 POST 提交');
        }
        if (!LegacyRouter::sameOrigin($_SERVER)) {
            throw new AppError('no_permission', 403, '没有操作权限');
        }
        (new AdminService())->audit(array_merge($_GET, $_POST));
        header('Location: /admin/modules?ok=audited');
    } catch (AppError $e) {
        header('Location: /admin/modules?error=' . rawurlencode($e->errorCode));
    }
    exit;
}

if ((string) $do === '') {
    header('Location: /admin');
    exit;
}

$target = UrlMap::pageTarget((string) $do, $act, $_GET);
if ($target === null) {
    http_response_code(404);
    exit('Not Found');
}
header('Location: ' . $target);
