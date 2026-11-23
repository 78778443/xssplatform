<?php
final class FrontendShell
{
    public static function render(): void
    {
        header_remove('X-Powered-By');
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; frame-ancestors 'self'");
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
        if ($https) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        $file = ROOT_PATH . '/dist/index.html';
        if (is_file($file)) {
            readfile($file);
            return;
        }
        echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>XSS平台</title></head><body><div id="root">'
            . '前端未构建，请运行 cd frontend &amp;&amp; npm run build'
            . '</div></body></html>';
    }
}
