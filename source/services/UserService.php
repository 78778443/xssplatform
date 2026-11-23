<?php
final class UserService
{
    private function requireUser(): User
    {
        $user = current_user();
        if ($user->userId <= 0) {
            throw new AppError('not_logged_in', 401, '未登录或已超时');
        }
        return $user;
    }

    private function flags(User $user): array
    {
        $row = db()->FirstRow('SELECT message FROM ' . Tb('user') . ' WHERE id=?', [$user->userId]);
        $message = (string) ($row['message'] ?? '');
        $parts = explode('|', $message);
        return [
            'emsg' => (string) ($parts[0] ?? '0'),
            'dmsg' => (string) ($parts[2] ?? '0'),
        ];
    }

    public function settingsGet(): array
    {
        $user = $this->requireUser();
        $row = db()->FirstRow('SELECT email,dingding FROM ' . Tb('user') . ' WHERE id=?', [$user->userId]);
        $flags = $this->flags($user);
        return [
            'email' => Display::decode((string) ($row['email'] ?? '')),
            'dingding' => Display::decode((string) ($row['dingding'] ?? '')),
            'emsg' => $flags['emsg'],
            'dmsg' => $flags['dmsg'],
        ];
    }

    public function settingsSave(array $input): array
    {
        $user = $this->requireUser();
        $emsg = $this->flag((string) ($input['emsg'] ?? ''));
        $dmsg = $this->flag((string) ($input['dmsg'] ?? ''));
        $dingding = StripStr((string) ($input['dingding'] ?? ''));
        // middle segment belongs to the removed SMS channel; keep it as 0 for old data.
        db()->Update(Tb('user'), [
            'message' => $emsg . '|0|' . $dmsg,
            'dingding' => $dingding,
        ], 'id=?', [$user->userId]);
        return [];
    }

    private function flag(string $value): string
    {
        return ($value === '1' || $value === 'on') ? '1' : '0';
    }

    public function invites(): array
    {
        $user = $this->requireUser();
        $invites = db()->Dataset(
            'SELECT id,inviteKey as code,isWooyun,addTime FROM ' . Tb('invite_reg')
            . ' WHERE userId=? AND isUsed=0 ORDER BY id DESC',
            [$user->userId]
        );
        $wooyun = [];
        $other = [];
        foreach ($invites as $v) {
            if ((int) $v['isWooyun'] === 1) {
                $wooyun[] = $v;
            } else {
                $other[] = $v;
            }
        }
        return ['wooyun' => $wooyun, 'other' => $other];
    }

    public function createInvite(array $input): array
    {
        $user = $this->requireUser();
        if (!$user->isAdmin()) {
            throw new AppError('no_permission', 403, '没有操作权限');
        }
        $limit = 100;
        $count = (int) db()->FirstValue(
            'SELECT COUNT(*) FROM ' . Tb('invite_reg') . ' WHERE userId=? AND isUsed=0',
            [$user->userId]
        );
        if ($count >= $limit) {
            throw new AppError('invite_limit', 400, '最多可生成' . $limit . '条未使用的邀请链接');
        }
        $isWooyun = (int) ($input['isWooyun'] ?? 0) === 1 ? 1 : 0;
        db()->Insert(Tb('invite_reg'), [
            'userId' => $user->userId,
            'inviteKey' => bin2hex(random_bytes(16)),
            'isWooyun' => $isWooyun,
            'addTime' => time(),
        ]);
        return [];
    }
}
