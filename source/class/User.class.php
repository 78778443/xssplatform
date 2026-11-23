<?php
/**
 * User.class.php 用户操作类
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */
if (!defined('IN_OLDCMS')) {
    die('Access Denied');
}

class User
{
    public int $userId = 0;
    public int $adminLevel = 0;
    public string $userName = '';
    public string $token = '';

    private DB_Mysql $db;
    private string $tbUser;
    private string $tbSession;
    private int $expiryTime = 3600;
    private string $ocKey = '';

    public function __construct(DB_Mysql $db)
    {
        $this->db = $db;
        $this->tbUser = $db->tbPrefix . 'user';
        $this->tbSession = $db->tbPrefix . 'session';
        $expires = (int) (defined('EXPIRES') ? EXPIRES : 3600);
        if ($expires > 0) {
            $this->expiryTime = $expires;
        }

        $cookie = $_COOKIE['ocKey'] ?? '';
        if (!is_string($cookie) || !preg_match('/^[a-f0-9]{32,64}$/', $cookie)) {
            return;
        }
        $row = $this->db->FirstRow(
            'SELECT userId,token,data FROM ' . $this->tbSession . ' WHERE ocKey=? AND expires>?',
            [$cookie, time()]
        );
        if (empty($row)) {
            return;
        }
        $data = Security::safeUnserialize((string) $row['data']);
        if (!is_array($data)) {
            return;
        }
        $this->ocKey = $cookie;
        $this->token = (string) $row['token'];
        $this->userId = (int) ($data['userId'] ?? $row['userId'] ?? 0);
        $this->adminLevel = (int) ($data['adminLevel'] ?? 0);
        $this->userName = (string) ($data['userName'] ?? '');
        if ($this->userId <= 0) {
            return;
        }
        $this->db->Execute(
            'UPDATE ' . $this->tbSession . ' SET updateTime=?,expires=expires+? WHERE ocKey=?',
            [time(), $this->expiryTime, $cookie]
        );
    }

    /* 登录界面 */
    public function ToLogin(): void
    {
        header('Location: ' . URL_ROOT . '/index.php?do=login');
        exit;
    }

    /* 登录 */
    public function login(string $username = '', string $password = '', int $autoLogin = 0): bool
    {
        $field = strpos($username, '@') !== false ? 'email' : 'userName';
        $row = $this->db->FirstRow(
            'SELECT id,adminLevel,userName,email,userPwd FROM ' . $this->tbUser . ' WHERE ' . $field . '=?',
            [$username]
        );
        if (empty($row)) {
            return false;
        }

        $stored = (string) $row['userPwd'];
        $ok = password_verify($password, $stored);
        if (!$ok && preg_match('/^[a-f0-9]{32}$/i', $stored) && hash_equals(OCEncrypt($password), $stored)) {
            $ok = true;
            $this->db->Execute(
                'UPDATE ' . $this->tbUser . ' SET userPwd=? WHERE id=?',
                [password_hash($password, PASSWORD_DEFAULT), (int) $row['id']]
            );
        }
        if (!$ok) {
            return false;
        }

        if ($autoLogin === 1) {
            $this->expiryTime = 360 * 86400;
        }

        $this->db->Execute('DELETE FROM ' . $this->tbSession . ' WHERE expires<?', [time()]);

        $ocKey = bin2hex(random_bytes(24));
        $token = bin2hex(random_bytes(16));
        $data = serialize([
            'userId' => (int) $row['id'],
            'adminLevel' => (int) $row['adminLevel'],
            'userName' => (string) $row['userName'],
        ]);
        $this->db->Insert($this->tbSession, [
            'userId' => (int) $row['id'],
            'ocKey' => $ocKey,
            'token' => $token,
            'ip' => IP(),
            'data' => $data,
            'expires' => time() + $this->expiryTime,
            'updateTime' => time(),
            'addTime' => time(),
        ]);
        $this->db->Execute(
            'UPDATE ' . $this->tbUser . ' SET loginTime=? WHERE id=?',
            [time(), (int) $row['id']]
        );

        $this->userId = (int) $row['id'];
        $this->adminLevel = (int) $row['adminLevel'];
        $this->userName = (string) $row['userName'];
        $this->token = $token;
        $this->ocKey = $ocKey;

        OCSetCookie('ocKey', $ocKey, time() + $this->expiryTime);
        return true;
    }

    /* 退出 */
    public function logout(): bool
    {
        if ($this->userId > 0) {
            $this->db->Execute('DELETE FROM ' . $this->tbSession . ' WHERE userId=?', [$this->userId]);
        }
        OCSetCookie('ocKey', '', -3600);
        $this->userId = 0;
        $this->adminLevel = 0;
        $this->userName = '';
        $this->token = '';
        $this->ocKey = '';
        return true;
    }

    /* 验证操作 token */
    public function checkToken(string $token = ''): bool
    {
        return $this->token !== '' && hash_equals($this->token, $token);
    }

    public function isAdmin(): bool
    {
        return $this->adminLevel > 0;
    }

    public function sessionUser(): array
    {
        return [
            'id' => $this->userId,
            'userName' => $this->userName,
            'adminLevel' => $this->adminLevel,
            'token' => $this->token,
        ];
    }
}
