<?php
final class KeepSessionService
{
    public function record(string $urlKey, string $url, string $cookie): void
    {
        if ($urlKey === '' || $url === '' || $cookie === '') {
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
