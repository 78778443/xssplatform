<?php
final class AdminService
{
    private function requireAdmin(): User
    {
        $user = current_user();
        if ($user->userId <= 0) {
            throw new AppError('not_logged_in', 401, '未登录或已超时');
        }
        if (!$user->isAdmin()) {
            throw new AppError('no_permission', 403, '没有操作权限');
        }
        return $user;
    }

    public function overview(): array
    {
        $this->requireAdmin();
        $db = db();
        $userTable = Tb('user');
        $sessionTable = Tb('session');
        $expires = (int) Config::get('expires', 3600);

        $userCount = (int) $db->FirstValue('SELECT COUNT(*) FROM ' . $userTable);
        $onlineCount = (int) $db->FirstValue(
            'SELECT COUNT(DISTINCT userId) FROM ' . $sessionTable . ' WHERE updateTime>?',
            [time() - $expires]
        );
        $onlineUsers = $db->Dataset(
            'SELECT DISTINCT u.id,u.userName FROM ' . $sessionTable . ' s INNER JOIN ' . $userTable
            . ' u ON u.id=s.userId WHERE s.updateTime>?',
            [time() - $expires]
        );
        foreach ($onlineUsers as $k => $v) {
            $onlineUsers[$k]['userName'] = Display::decode((string) $v['userName']);
        }

        $sysInfo = [
            'serverOS' => PHP_OS,
            'serverSoftware' => (string) ($_SERVER['SERVER_SOFTWARE'] ?? ''),
            'phpVersion' => 'PHP v' . PHP_VERSION,
            'mysqlVersion' => 'MySQL ' . (string) $db->FirstValue('SELECT VERSION()'),
        ];

        return [
            'userCount' => $userCount,
            'onlineCount' => $onlineCount,
            'onlineUsers' => $onlineUsers,
            'sysInfo' => $sysInfo,
        ];
    }

    public function modules(array $input): array
    {
        $this->requireAdmin();
        $db = db();
        $pageSize = 20;
        $pno = max(1, (int) ($input['pNO'] ?? 1));
        $total = (int) $db->FirstValue('SELECT COUNT(*) FROM ' . Tb('module') . ' WHERE isOpen=1');
        $items = $db->Dataset(
            'SELECT m.*,u.userName AS userName FROM ' . Tb('module') . ' m INNER JOIN ' . Tb('user')
            . ' u ON u.id=m.userId WHERE m.isOpen=1 ORDER BY m.id DESC LIMIT ' . (($pno - 1) * $pageSize) . ',' . $pageSize
        );
        foreach ($items as $k => $v) {
            $items[$k]['title'] = Display::decode((string) $v['title']);
            $items[$k]['description'] = Display::decode((string) $v['description']);
            $items[$k]['userName'] = Display::decode((string) $v['userName']);
        }
        return ['items' => $items, 'total' => $total, 'pno' => $pno, 'pageSize' => $pageSize];
    }

    public function audit(array $input): array
    {
        $user = $this->requireAdmin();
        $id = (int) ($input['id'] ?? 0);
        $isAudit = (int) ($input['isAudit'] ?? 0) === 1 ? 1 : 0;
        $module = db()->FirstRow('SELECT id FROM ' . Tb('module') . ' WHERE id=?', [$id]);
        if (empty($module)) {
            throw new AppError('module_not_found', 404, '模块不存在或没有权限');
        }
        db()->Update(Tb('module'), [
            'isAudit' => $isAudit,
            'managerId' => $user->userId,
            'managerName' => StripStr($user->userName),
        ], 'id=?', [$id]);
        return [];
    }
}
