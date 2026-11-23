<?php
final class AuthService
{
    public function session(): array
    {
        $user = current_user();
        $show = Config::get('show', []);
        return [
            'user' => $user->userId > 0 ? $user->sessionUser() : null,
            'csrf' => $user->userId > 0 ? $user->token : '',
            'config' => [
                'sitename' => (string) ($show['sitename'] ?? 'XSS平台'),
                'sitedesc' => (string) ($show['sitedesc'] ?? ''),
                'register' => (string) Config::get('register', 'normal'),
                'urlroot' => (string) Config::get('urlroot', ''),
                'urlrewrite' => (bool) Config::get('urlrewrite', false),
                'expires' => (int) Config::get('expires', 3600),
            ],
            'installed' => true,
        ];
    }

    public function login(array $input): array
    {
        $user = current_user();
        if ($user->userId > 0) {
            throw new AppError('already_logged_in', 400, '已经登录');
        }
        $rawUsername = trim((string) ($input['user'] ?? ''));
        $username = StripStr((string) ($input['user'] ?? ''));
        $password = (string) ($input['pwd'] ?? '');
        if ($username === '') {
            throw new AppError('login_empty_user', 400, '用户/邮箱不能为空');
        }
        if ($password === '') {
            throw new AppError('login_empty_pwd', 400, '密码不能为空');
        }

        $identifier = $this->normalizeIdentifier($rawUsername);
        $accountBucket = RateLimiter::bucket('login-account', $identifier, IP());
        $ipBucket = RateLimiter::bucket('login-ip', IP());
        $retryAfter = max(
            RateLimiter::retryAfter($accountBucket),
            RateLimiter::retryAfter($ipBucket)
        );
        if ($retryAfter > 0) {
            throw $this->throttled($retryAfter);
        }

        $auto = (int) ($input['auto'] ?? 0) === 1 ? 1 : 0;
        if (!$user->login($username, $password, $auto)) {
            $retryAfter = max(
                RateLimiter::hit($accountBucket, 5, 900, 900),
                RateLimiter::hit($ipBucket, 25, 900, 900)
            );
            if ($retryAfter > 0) {
                throw $this->throttled($retryAfter);
            }
            throw new AppError('login_failed', 400, '登录失败,请检查用户/邮箱或密码');
        }
        RateLimiter::clear($accountBucket);
        return [
            'user' => $user->sessionUser(),
            'csrf' => $user->token,
        ];
    }

    private function normalizeIdentifier(string $value): string
    {
        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }

    private function enforceRate(string $scope, int $limit, int $window, int $block): void
    {
        $bucket = RateLimiter::bucket($scope, IP());
        $retryAfter = RateLimiter::retryAfter($bucket);
        if ($retryAfter === 0) {
            $retryAfter = RateLimiter::hit($bucket, $limit, $window, $block);
        }
        if ($retryAfter > 0) {
            throw $this->throttled($retryAfter);
        }
    }

    private function throttled(int $retryAfter): AppError
    {
        $retryAfter = max(1, $retryAfter);
        if (!headers_sent()) {
            header('Retry-After: ' . $retryAfter);
        }
        return new AppError(
            'too_many_attempts',
            429,
            '尝试次数过多，请 ' . $retryAfter . ' 秒后再试'
        );
    }

    public function logout(): array
    {
        current_user()->logout();
        return [];
    }

    public function registerCheck(array $input): array
    {
        $this->enforceRate('register-check-ip', 120, 600, 300);
        $db = db();
        $username = StripStr((string) ($input['user'] ?? ''));
        $email = StripStr((string) ($input['email'] ?? ''));
        $key = StripStr((string) ($input['key'] ?? ''));

        $userExisted = (int) $db->FirstValue('SELECT COUNT(*) FROM ' . Tb('user') . ' WHERE userName=?', [$username]);
        $emailExisted = (int) $db->FirstValue('SELECT COUNT(*) FROM ' . Tb('user') . ' WHERE email=?', [$email]);
        $keyError = 0;
        if (defined('REGISTER') && REGISTER === 'invite') {
            if ($key === '' || !$db->FirstRow('SELECT id FROM ' . Tb('invite_reg') . ' WHERE inviteKey=? AND isUsed=0', [$key])) {
                $keyError = 1;
            }
        }
        return [
            'userExisted' => $userExisted,
            'emailExisted' => $emailExisted,
            'keyError' => $keyError,
        ];
    }

    public function register(array $input): array
    {
        $db = db();
        $user = current_user();
        if (defined('REGISTER') && REGISTER === 'close') {
            throw new AppError('register_closed', 400, '注册功能已关闭');
        }
        if ($user->userId > 0) {
            throw new AppError('already_logged_in', 400, '您已登录,不能进行注册');
        }
        $this->enforceRate('register-ip', 60, 3600, 900);

        $username = StripStr((string) ($input['user'] ?? ''));
        $email = StripStr((string) ($input['email'] ?? ''));
        $rawPwd = (string) ($input['pwd'] ?? '');
        $key = StripStr((string) ($input['key'] ?? ''));

        $inviteRow = null;
        if ($key !== '') {
            $inviteRow = $db->FirstRow('SELECT id,userId FROM ' . Tb('invite_reg') . ' WHERE inviteKey=? AND isUsed=0', [$key]);
        }
        if (defined('REGISTER') && REGISTER === 'invite') {
            if ($key === '') {
                throw new AppError('register_invite_only', 400, '本站目前仅能邀请注册');
            }
            if (empty($inviteRow)) {
                throw new AppError('invite_invalid', 400, '你的邀请码不正确或已作废');
            }
        }

        if ($username === '' || !Validate::username($username)) {
            throw new AppError('username_format', 400, '用户格式不正确');
        }
        if ($email === '' || !Validate::email($email)) {
            throw new AppError('email_format', 400, '邮箱格式不正确');
        }
        if ($rawPwd === '' || !Validate::password(StripStr($rawPwd))) {
            throw new AppError('pwd_format', 400, '密码应为6-20位字符');
        }

        if ((int) $db->FirstValue('SELECT COUNT(*) FROM ' . Tb('user') . ' WHERE userName=?', [$username]) > 0) {
            throw new AppError('username_exists', 400, "用户{$username}已存在");
        }
        if ((int) $db->FirstValue('SELECT COUNT(*) FROM ' . Tb('user') . ' WHERE email=?', [$email]) > 0) {
            throw new AppError('email_exists', 400, "邮箱{$email}已存在");
        }

        try {
            $id = $db->Insert(Tb('user'), [
                'userName' => $username,
                'userPwd' => password_hash(StripStr($rawPwd), PASSWORD_DEFAULT),
                'email' => $email,
                'addTime' => time(),
            ]);
        } catch (Throwable $e) {
            // The unique indexes can still race with a concurrent registration.
            if (stripos($e->getMessage(), 'Duplicate') !== false) {
                throw new AppError('username_exists', 400, '用户名或邮箱已被注册');
            }
            throw $e;
        }
        if ($id <= 0) {
            throw new AppError('register_failed', 500, '出错了,请与管理员联系');
        }
        if (!empty($inviteRow)) {
            $db->Execute(
                'UPDATE ' . Tb('invite_reg') . ' SET isUsed=1,regUserId=?,regTime=? WHERE id=?',
                [$id, time(), (int) $inviteRow['id']]
            );
        }

        // Old flow ignored the result of the auto-login; keep the same behaviour.
        $user->login($username, $rawPwd, 1);

        return [
            'user' => $user->userId > 0 ? $user->sessionUser() : null,
            'csrf' => $user->userId > 0 ? $user->token : '',
        ];
    }
}
