<?php
final class Config
{
    private static array $data = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            throw new RuntimeException('Config file not found: ' . $file);
        }
        $config = [];
        $returned = include $file;
        if (is_array($returned)) {
            $config = $returned;
        }
        if ($config === []) {
            throw new RuntimeException('Config file did not define a config array: ' . $file);
        }
        self::$data = $config;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$data[$key] ?? $default;
    }

    public static function all(): array
    {
        return self::$data;
    }

    public static function defaults(): array
    {
        return [
            'dbHost' => '127.0.0.1',
            'dbUser' => 'root',
            'dbPwd' => '',
            'database' => 'xssplatform',
            'charset' => 'utf8',
            'tbPrefix' => 'oc_',
            'dbType' => 'mysql',
            'register' => 'normal',
            'urlroot' => '',
            'urlrewrite' => false,
            'timezone' => 'Asia/Shanghai',
            'expires' => 3600,
            'debug' => false,
            'show' => [
                'sitename' => 'XSS平台',
                'sitedesc' => '',
                'keywords' => '',
                'description' => '',
                'adminmail' => '',
            ],
            'email' => [
                'host' => '',
                'username' => '',
                'password' => '',
                'port' => 465,
                'secure' => 'ssl',
                'auth' => true,
                'fromName' => 'Mailer',
            ],
            'feishu' => [
                'keyword' => '最新消息',
                'endpoint' => '',
            ],
        ];
    }
}
