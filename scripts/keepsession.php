<?php
/**
 * keepsession.php keep session 执行（cron）
 * ----------------------------------------------------------------
 * 每 300 秒对未刷新的 keepsession 记录发起一次带 Cookie 的请求。
 * 用法：XSSP_CONFIG=/path/to/config.php php scripts/keepsession.php
 */
define('ROOT_PATH', dirname(__DIR__));
define('IN_OLDCMS', true);

require ROOT_PATH . '/source/bootstrap.php';

$db = db();
$rows = $db->Dataset('SELECT * FROM ' . Tb('keepsession'));

foreach ($rows as $row) {
    if (time() <= (int) $row['updateTime'] + 300) {
        continue;
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, (string) $row['url']);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Cookie: ' . urldecode((string) $row['cookie'])]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    if ($result === false) {
        error_log('keepsession fetch failed: ' . curl_error($ch));
    }
    curl_close($ch);
    $db->Execute(
        'UPDATE ' . Tb('keepsession') . ' SET updateTime=? WHERE id=?',
        [time(), (int) $row['id']]
    );
}
