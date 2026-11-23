<?php
/**
 * function.php 方法库
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */
if (!defined('IN_OLDCMS')) {
    die('Access Denied');
}

function load_config_array(string $file): array
{
    if (!is_file($file)) {
        throw new RuntimeException('Config file not found: ' . $file);
    }
    $config = [];
    $returned = include $file;
    if (is_array($returned)) {
        return $returned;
    }
    return is_array($config) ? $config : [];
}

/**
 * DBConnect 数据库连接方法（进程内单例，按配置文件缓存）
 */
function DBConnect($configFile = '')
{
    static $connections = [];

    $configFile = $configFile !== '' ? $configFile : (getenv('XSSP_CONFIG') ?: ROOT_PATH . '/config.php');
    if (isset($connections[$configFile])) {
        return $connections[$configFile];
    }

    $config = class_exists('Config') ? Config::all() : [];
    if ($config === []) {
        $config = load_config_array($configFile);
    }

    require_once ROOT_PATH . '/source/class/DB.class.php';
    $db = BlueDB::DB($config['dbType'] ?? 'mysql');
    if (!is_object($db) || !$db->Connect(
        $config['dbHost'] ?? 'localhost',
        $config['dbUser'] ?? 'root',
        $config['dbPwd'] ?? '',
        $config['database'] ?? '',
        $config['charset'] ?? 'utf8',
        $config['tbPrefix'] ?? ''
    )) {
        throw new RuntimeException('Database connection failed' . (is_object($db) ? ': ' . $db->Error() : ''));
    }
    $connections[$configFile] = $db;
    return $db;
}

/**
 * OCEncrypt legacy md5 password (only used to verify old hashes)
 */
function OCEncrypt($str)
{
    return md5('OldCMS|' . $str);
}

/* OCSetCookie setcookie 封装 */
function OCSetCookie($name = '', $value = '', $expires = 0, $path = '/', $domain = '', $httponly = true)
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    $port = (int) ($_SERVER['SERVER_PORT'] ?? 0);
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $port === 443;
    setcookie((string) $name, (string) $value, [
        'expires' => (int) $expires,
        'path' => (string) $path,
        'domain' => (string) $domain,
        'secure' => $secure,
        'httponly' => (bool) $httponly,
        'samesite' => 'Lax',
    ]);
}

/* 获得IP */
function IP()
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/* StripStr 过滤字符（保持旧数据格式） */
function StripStr($str)
{
    return addslashes(htmlspecialchars((string) $str, ENT_QUOTES));
}

/**
 * Val 获得提交的值
 *
 * @param  $name    string
 * @param  $method  string GET/POST/COOKIE/REQUEST/SERVER
 * @param  $type    string/int 0=string, 1=int, 2=raw
 * @param  $isArray int 0=scalar, 1=array
 */
function Val($name, $method = 'GET', $type = 0, $isArray = 0)
{
    if ($name == '' || !is_string($name)) {
        return '';
    }
    $method = strtoupper($method);
    switch ($method) {
    case 'GET':
        $value = $_GET[$name] ?? '';
        break;
    case 'POST':
        $value = $_POST[$name] ?? '';
        break;
    case 'COOKIE':
        $value = $_COOKIE[$name] ?? '';
        break;
    case 'REQUEST':
        $value = $_REQUEST[$name] ?? '';
        break;
    case 'SERVER':
        $value = $_SERVER[$name] ?? '';
        break;
    default:
        return '';
    }
    $isArray = intval($isArray);
    if ($isArray === 0 && is_array($value)) {
        // Scalar callers must never receive an array (or "Array" via a cast).
        $value = '';
    }
    switch ($type) {
    case 0:
    case 'string':
        $value = ($isArray == 0) ? StripStr($value) : array_map('StripStr', (array) $value);
        break;
    case 1:
    case 'int':
        $value = ($isArray == 0) ? intval($value) : array_map('intval', (array) $value);
        break;
    case 2:
    default:
        break;
    }
    return $value;
}

/* json_encode */
function JsonEncode($value)
{
    return json_encode($value);
}

/* Tb 获取 table name */
function Tb($name)
{
    return TABLE_PREFIX . $name;
}

/* 短网址字符 */
function ShortUrlCode($existed = array(), $num = 6)
{
    $str = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $len = strlen($str);
    $code = '';
    for ($i = 0; $i < $num; $i++) {
        $code .= $str[random_int(0, $len - 1)];
    }
    if (in_array($code, $existed, true)) {
        $code = ShortUrlCode($existed, $num);
    }
    return $code;
}

/* 发送邮件（实现见 Notify，Task 12 替换为 PHPMailer 6） */
function SendMail($to = '', $subject = '', $body = '')
{
    return Notify::sendMail((string) $to, (string) $subject, (string) $body);
}

/* 飞书通知（兼容旧调用） */
function feishuNotice($message, $webhook)
{
    return Notify::sendFeishu((string) $message, (string) $webhook);
}
