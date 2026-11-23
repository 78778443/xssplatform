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
            'fmsg' => (string) ($parts[2] ?? '0'),
        ];
    }

    public function settingsGet(): array
    {
        $user = $this->requireUser();
        $row = db()->FirstRow('SELECT email,feishu FROM ' . Tb('user') . ' WHERE id=?', [$user->userId]);
        $flags = $this->flags($user);
        return [
            'email' => Display::decode((string) ($row['email'] ?? '')),
            'feishu' => Display::decode((string) ($row['feishu'] ?? '')),
            'emsg' => $flags['emsg'],
            'fmsg' => $flags['fmsg'],
        ];
    }

    public function settingsSave(array $input): array
    {
        $user = $this->requireUser();
        $emsg = $this->flag((string) ($input['emsg'] ?? ''));
        $fmsg = $this->flag((string) ($input['fmsg'] ?? $input['dmsg'] ?? ''));
        $webhook = trim((string) ($input['feishu'] ?? $input['dingding'] ?? ''));
        if ($webhook !== '' && !Notify::validWebhook($webhook)) {
            throw new AppError('feishu_webhook_invalid', 400, '飞书机器人 Webhook 格式不正确');
        }
        // middle segment belongs to the removed SMS channel; keep it as 0 for old data.
        db()->Update(Tb('user'), [
            'message' => $emsg . '|0|' . $fmsg,
            'feishu' => StripStr($webhook),
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
            'SELECT id,inviteKey as code,addTime FROM ' . Tb('invite_reg')
            . ' WHERE userId=? AND isUsed=0 ORDER BY id DESC',
            [$user->userId]
        );
        return ['other' => $invites];
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
        db()->Insert(Tb('invite_reg'), [
            'userId' => $user->userId,
            'inviteKey' => bin2hex(random_bytes(16)),
            'addTime' => time(),
        ]);
        return [];
    }
}
