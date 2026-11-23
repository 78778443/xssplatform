<?php
/**
 * keepsession.php keep session 执行（cron）
 * ----------------------------------------------------------------
 * 每 300 秒对未刷新的 keepsession 记录发起一次带 Cookie 的请求。
 * 用法：XSSP_CONFIG=/path/to/config.php php scripts/keepsession.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('ROOT_PATH', dirname(__DIR__));
define('IN_OLDCMS', true);

require ROOT_PATH . '/source/bootstrap.php';

/**
 * Only plain http/https URLs are allowed; private and reserved addresses are
 * rejected so a stored keepsession row cannot be used to reach the intranet.
 */
function keepsession_url_allowed(string $url): bool
{
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return false;
    }
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return false;
    }
    $host = (string) $parts['host'];
    $ips = [];
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $ips[] = $host;
    } else {
        $resolved = gethostbynamel($host);
        if (is_array($resolved)) {
            $ips = $resolved;
        }
    }
    if ($ips === []) {
        return false;
    }
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
    }
    return true;
}

$db = db();
$rows = $db->Dataset('SELECT * FROM ' . Tb('keepsession'));

foreach ($rows as $row) {
    if (time() <= (int) $row['updateTime'] + 300) {
        continue;
    }
    if (!keepsession_url_allowed((string) $row['url'])) {
        error_log('keepsession skipped unsafe url: ' . (string) $row['url']);
        continue;
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, (string) $row['url']);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Cookie: ' . urldecode((string) $row['cookie'])]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    $failed = $result === false;
    if ($failed) {
        error_log('keepsession fetch failed: ' . curl_error($ch));
    }
    curl_close($ch);
    if ($failed) {
        continue;
    }
    $db->Execute(
        'UPDATE ' . Tb('keepsession') . ' SET updateTime=? WHERE id=?',
        [time(), (int) $row['id']]
    );
}
