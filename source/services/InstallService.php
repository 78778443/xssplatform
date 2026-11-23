<?php
final class InstallService
{
    public function status(): array
    {
        $configFile = ROOT_PATH . '/config.php';
        $configWritable = is_writable($configFile)
            || (!file_exists($configFile) && is_writable(ROOT_PATH));
        return [
            'installed' => file_exists(ROOT_PATH . '/install/install.lock'),
            'configWritable' => $configWritable,
            'phpVersion' => PHP_VERSION,
            'extensions' => [
                'mysqli' => extension_loaded('mysqli'),
                'curl' => extension_loaded('curl'),
                'openssl' => extension_loaded('openssl'),
            ],
        ];
    }

    public function testDb(array $input): void
    {
        $link = $this->connect($input);
        mysqli_close($link);
    }

    public function install(array $input): array
    {
        if (file_exists(ROOT_PATH . '/install/install.lock')) {
            throw new AppError('no_permission', 403, '已经安装');
        }
        if (!is_writable(ROOT_PATH . '/config.php') && !is_writable(ROOT_PATH)) {
            throw new AppError('install_failed', 400, '配置文件不可写');
        }

        $dbHost = trim((string) ($input['dbHost'] ?? ''));
        $dbUser = trim((string) ($input['dbUser'] ?? ''));
        $dbPwd = (string) ($input['dbPwd'] ?? '');
        $dbName = trim((string) ($input['dbName'] ?? ''));
        $username = trim((string) ($input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if (!preg_match('/^[A-Za-z0-9.:-]+$/', $dbHost) || $dbName === '' || $dbUser === '' || $username === '' || $password === '') {
            throw new AppError('install_failed', 400, '请填写完整的安装信息');
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
            throw new AppError('install_failed', 400, '数据库名只能包含字母、数字和下划线');
        }

        $link = $this->connect($input);
        $create = 'CREATE DATABASE IF NOT EXISTS `' . $dbName . '` DEFAULT CHARSET utf8 COLLATE utf8_general_ci';
        if (!mysqli_query($link, $create)) {
            $error = mysqli_error($link);
            mysqli_close($link);
            throw new AppError('install_failed', 400, '创建数据库失败: ' . $error);
        }
        if (!mysqli_select_db($link, $dbName)) {
            $error = mysqli_error($link);
            mysqli_close($link);
            throw new AppError('install_failed', 400, '选择数据库失败: ' . $error);
        }
        mysqli_set_charset($link, 'utf8');

        $urlroot = $this->sanitizeUrlRoot((string) ($input['urlroot'] ?? $this->detectUrlRoot()));
        $hostForSql = (string) parse_url($urlroot, PHP_URL_HOST);
        $sql = (string) file_get_contents(ROOT_PATH . '/xssplatform.sql');
        if ($hostForSql !== '') {
            $sql = preg_replace('/http:\/\/(.*?)\//', 'http://' . $hostForSql . '/', $sql);
        }
        if (!mysqli_multi_query($link, $sql)) {
            $error = mysqli_error($link);
            mysqli_close($link);
            throw new AppError('install_failed', 400, '导入数据失败: ' . $error);
        }
        do {
            if ($result = mysqli_store_result($link)) {
                mysqli_free_result($result);
            }
            if (mysqli_errno($link)) {
                $error = mysqli_error($link);
                mysqli_close($link);
                throw new AppError('install_failed', 400, '导入数据失败: ' . $error);
            }
        } while (mysqli_more_results($link) && mysqli_next_result($link));

        $result = mysqli_query($link, 'SELECT id FROM oc_user ORDER BY id LIMIT 1');
        $row = $result ? mysqli_fetch_row($result) : null;
        if ($result) {
            mysqli_free_result($result);
        }
        $adminId = (int) ($row[0] ?? 0);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($adminId > 0) {
            $stmt = mysqli_prepare($link, 'UPDATE oc_user SET userName=?,userPwd=?,adminLevel=1 WHERE id=?');
            mysqli_stmt_bind_param($stmt, 'ssi', $username, $hash, $adminId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } else {
            $stmt = mysqli_prepare($link, 'INSERT INTO oc_user(adminLevel,userName,userPwd,addTime) VALUES(1,?,?,UNIX_TIMESTAMP())');
            mysqli_stmt_bind_param($stmt, 'ss', $username, $hash);
            mysqli_stmt_execute($stmt);
            $adminId = (int) mysqli_insert_id($link);
            mysqli_stmt_close($stmt);
        }
        mysqli_close($link);

        $config = Config::defaults();
        $config['dbHost'] = $dbHost;
        $config['dbUser'] = $dbUser;
        $config['dbPwd'] = $dbPwd;
        $config['database'] = $dbName;
        $config['urlroot'] = $urlroot;
        ConfigWriter::write(ROOT_PATH . '/config.php', $config);

        if (file_put_contents(ROOT_PATH . '/install/install.lock', '') === false) {
            throw new AppError('install_failed', 500, '无法写入 install.lock');
        }

        return ['adminId' => $adminId];
    }

    private function connect(array $input)
    {
        mysqli_report(MYSQLI_REPORT_OFF);
        $host = trim((string) ($input['dbHost'] ?? ''));
        $port = 3306;
        if (strpos($host, ':') !== false) {
            [$host, $port] = explode(':', $host, 2);
            $port = (int) $port;
        }
        $link = @new mysqli($host, (string) ($input['dbUser'] ?? ''), (string) ($input['dbPwd'] ?? ''), null, $port);
        if (!empty($link->connect_errno)) {
            throw new AppError('install_failed', 400, '数据库信息有误: ' . $link->connect_error);
        }
        return $link;
    }

    private function detectUrlRoot(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        if (!preg_match('/^[A-Za-z0-9.:-]+$/', $host)) {
            $host = 'localhost';
        }
        return $scheme . '://' . $host;
    }

    private function sanitizeUrlRoot(string $urlroot): string
    {
        $parts = parse_url($urlroot);
        $scheme = $parts['scheme'] ?? '';
        $host = $parts['host'] ?? '';
        $port = $parts['port'] ?? null;
        if (!in_array($scheme, ['http', 'https'], true) || $host === '' || !preg_match('/^[A-Za-z0-9.:-]+$/', $host)) {
            return $this->detectUrlRoot();
        }
        return $scheme . '://' . $host . ($port !== null ? ':' . (int) $port : '');
    }
}
