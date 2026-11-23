<?php
final class ReceiveService
{
    /**
     * Store a received payload. Returns the redirect target, or null when the
     * project does not exist (the legacy endpoint exits without output).
     */
    public function receive(array $request, array $server): ?string
    {
        $db = db();
        $urlKey = Val('id', 'GET');
        if ($urlKey === '') {
            return null;
        }
        $project = $db->FirstRow('SELECT * FROM ' . Tb('project') . ' WHERE urlKey=?', [$urlKey]);
        if (empty($project)) {
            return null;
        }

        $keys = [];
        $moduleIds = json_decode((string) $project['modules'], true);
        if (is_array($moduleIds) && $moduleIds !== []) {
            $ids = array_values(array_map('intval', $moduleIds));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = $ids;
            $params[] = (int) $project['userId'];
            $modules = $db->Dataset(
                'SELECT * FROM ' . Tb('module') . ' WHERE id IN (' . $placeholders . ')'
                . ' AND (userId=? OR (isOpen=1 AND isAudit=1))',
                $params
            );
            foreach ($modules as $module) {
                if (!empty($module['keys'])) {
                    $moduleKeys = json_decode((string) $module['keys'], true);
                    if (is_array($moduleKeys)) {
                        $keys = array_merge($keys, $moduleKeys);
                    }
                }
            }
        }

        $content = [];
        foreach ($keys as $key) {
            $key = (string) $key;
            $content[$key] = Val($key, 'REQUEST');
        }
        if (in_array('toplocation', $keys, true)) {
            $content['toplocation'] = !empty($content['toplocation'])
                ? $content['toplocation']
                : ($content['location'] ?? '');
        }

        $judgeCookie = in_array('cookie', $keys, true);
        $cookieHash = md5(
            $project['id'] . '_'
            . ($content['cookie'] ?? '') . '_'
            . ($content['location'] ?? '') . '_'
            . ($content['toplocation'] ?? '')
        );
        $cookieExisted = (int) $db->FirstValue(
            'SELECT COUNT(*) FROM ' . Tb('project_content') . ' WHERE projectId=? AND cookieHash=?',
            [(int) $project['id'], $cookieHash]
        );

        if (!$judgeCookie || $cookieExisted <= 0) {
            $referer = (string) ($server['HTTP_REFERER'] ?? '');
            $serverContent = [];
            $serverContent['HTTP_REFERER'] = $referer;
            $referers = @parse_url($referer);
            $domain = is_array($referers) && !empty($referers['host']) ? $referers['host'] : '';
            $domain = StripStr($domain);
            $serverContent['HTTP_REFERER'] = StripStr($referer);
            $serverContent['HTTP_USER_AGENT'] = StripStr((string) ($server['HTTP_USER_AGENT'] ?? ''));
            $userIp = (string) ($server['HTTP_X_FORWARDED_FOR'] ?? '');
            if ($userIp === '') {
                $userIp = (string) ($server['REMOTE_ADDR'] ?? '');
            }
            $serverContent['REMOTE_ADDR'] = StripStr($userIp);

            $db->Insert(Tb('project_content'), [
                'projectId' => (int) $project['id'],
                'content' => JsonEncode($content),
                'serverContent' => JsonEncode($serverContent),
                'domain' => $domain,
                'cookieHash' => $cookieHash,
                'num' => 1,
                'addTime' => time(),
            ]);

            $userInfo = $db->FirstRow('SELECT * FROM ' . Tb('user') . ' WHERE id=?', [(int) $project['userId']]);
            if (!empty($userInfo)) {
                $message = explode('|', (string) ($userInfo['message'] ?? ''));
                $cookie = (string) ($content['cookie'] ?? '');
                $sendStr = '尊敬的' . $userInfo['userName'] . '，您在' . URL_ROOT . ' 预订的猫饼干<br>Cookie:'
                    . $cookie . '<br>已经到货！<br>详情请登陆：' . URL_ROOT . ' 查看。';
                if (!empty($userInfo['email']) && ($message[0] ?? '0') === '1') {
                    Notify::sendMail((string) $userInfo['email'], URL_ROOT . '饼干商城', $sendStr);
                }
                if (!empty($userInfo['dingding']) && ($message[2] ?? '0') === '1') {
                    Notify::sendDing($sendStr, (string) $userInfo['dingding']);
                }
            }
        } else {
            $db->Execute(
                'UPDATE ' . Tb('project_content') . ' SET num=num+1,updateTime=? WHERE projectId=? AND cookieHash=?',
                [time(), (int) $project['id'], $cookieHash]
            );
        }

        return $this->safeReferer((string) ($server['HTTP_REFERER'] ?? ''));
    }

    private function safeReferer(string $referer): string
    {
        if ($referer === '') {
            return URL_ROOT !== '' ? URL_ROOT : '/';
        }
        $scheme = strtolower((string) parse_url($referer, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return URL_ROOT !== '' ? URL_ROOT : '/';
        }
        return $referer;
    }
}
