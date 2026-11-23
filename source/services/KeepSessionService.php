<?php
final class KeepSessionService
{
    public function record(string $urlKey, string $url, string $cookie): void
    {
        if ($urlKey === '' || $url === '' || $cookie === '') {
            return;
        }
        if (strlen($url) > 255 || strlen($cookie) > 8192) {
            return;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])
            || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
            return;
        }
        $host = (string) $parts['host'];
        if (filter_var($host, FILTER_VALIDATE_IP)
            && !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return;
        }
        $db = db();
        $project = $db->FirstRow('SELECT * FROM ' . Tb('project') . ' WHERE urlKey=?', [$urlKey]);
        if (empty($project)) {
            return;
        }
        $hash = md5($url . $cookie);
        $existed = (int) $db->FirstValue('SELECT COUNT(*) FROM ' . Tb('keepsession') . ' WHERE hash=?', [$hash]);
        if ($existed > 0) {
            return;
        }
        $sum = (int) $db->FirstValue(
            'SELECT COUNT(*) FROM ' . Tb('keepsession') . ' WHERE userId=?',
            [(int) $project['userId']]
        );
        if ($sum >= 10) {
            return;
        }
        $db->Insert(Tb('keepsession'), [
            'projectId' => (int) $project['id'],
            'userId' => (int) $project['userId'],
            'url' => $url,
            'cookie' => $cookie,
            'hash' => $hash,
            'addTime' => time(),
            'updateTime' => time(),
        ]);
    }
}
