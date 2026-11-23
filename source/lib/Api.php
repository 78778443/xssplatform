<?php
final class Api
{
    public static function handle(
        string $action,
        array $input,
        array $server,
        array $get,
        array $post,
        array $cookies
    ): void {
        $installed = file_exists(ROOT_PATH . '/install/install.lock');

        try {
            if ($installed) {
                require_once ROOT_PATH . '/source/bootstrap.php';
            }

            $input = self::parseInput($input, $server, $get, $post);

            if (!self::sameOrigin($server)) {
                throw new AppError('no_permission', 403, '没有操作权限');
            }

            switch ($action) {
            case 'meta':
            case 'session':
                $data = self::sessionPayload($installed, $action === 'session');
                break;
            case 'install.status':
            case 'install.testDb':
            case 'install.submit':
                if ($installed) {
                    throw new AppError('no_permission', 403, '已经安装');
                }
                require_once ROOT_PATH . '/source/lib/ConfigWriter.php';
                require_once ROOT_PATH . '/source/services/InstallService.php';
                $installer = new InstallService();
                if ($action === 'install.status') {
                    $data = $installer->status();
                } elseif ($action === 'install.testDb') {
                    $installer->testDb($input);
                    $data = [];
                } else {
                    $data = $installer->install($input);
                }
                break;
            default:
                throw new AppError('not_found', 404, '接口不存在');
            }

            self::respond($data, 200);
        } catch (AppError $e) {
            self::respondError($e);
        } catch (Throwable $e) {
            error_log('API error: ' . $e->getMessage());
            $debug = $installed && class_exists('Config') && Config::get('debug', false);
            self::respondError(new AppError(
                'internal_error',
                500,
                $debug ? $e->getMessage() : '内部错误'
            ));
        }
    }

    private static function parseInput(array $input, array $server, array $get, array $post): array
    {
        if ($input !== []) {
            return $input;
        }
        $contentType = (string) ($server['CONTENT_TYPE'] ?? '');
        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return array_merge($get, $post);
    }

    private static function sessionPayload(bool $installed, bool $includeSession): array
    {
        $defaults = class_exists('Config') ? Config::all() : [];
        if ($installed && $defaults === []) {
            $defaults = Config::all();
        }
        if (!$installed) {
            require_once ROOT_PATH . '/source/lib/Config.php';
            $defaults = Config::defaults();
        }
        $show = $defaults['show'] ?? [];
        $user = null;
        $csrf = '';
        if ($installed && function_exists('current_user')) {
            $current = current_user();
            if ($current->userId > 0) {
                $user = $current->sessionUser();
                $csrf = $current->token;
            }
        }
        return [
            'installed' => $installed,
            'config' => [
                'sitename' => (string) ($show['sitename'] ?? 'XSS平台'),
                'sitedesc' => (string) ($show['sitedesc'] ?? ''),
                'register' => (string) ($defaults['register'] ?? 'normal'),
                'urlroot' => (string) ($defaults['urlroot'] ?? ''),
                'urlrewrite' => (bool) ($defaults['urlrewrite'] ?? false),
                'expires' => (int) ($defaults['expires'] ?? 3600),
            ],
            'user' => $user,
            'csrf' => $csrf,
        ];
    }

    private static function sameOrigin(array $server): bool
    {
        $origin = (string) ($server['HTTP_ORIGIN'] ?? '');
        $referer = (string) ($server['HTTP_REFERER'] ?? '');
        $candidate = $origin !== '' ? $origin : $referer;
        if ($candidate === '') {
            return true;
        }
        $candidateHost = parse_url($candidate, PHP_URL_HOST);
        if (!is_string($candidateHost) || $candidateHost === '') {
            return false;
        }
        $expected = '';
        if (defined('URL_ROOT') && URL_ROOT !== '') {
            $expected = (string) parse_url(URL_ROOT, PHP_URL_HOST);
        }
        if ($expected === '') {
            $expected = (string) ($server['HTTP_HOST'] ?? '');
            $expected = explode(':', $expected)[0];
        }
        return $expected !== '' && hash_equals(strtolower($expected), strtolower($candidateHost));
    }

    public static function respond(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function respondError(AppError $error): void
    {
        http_response_code($error->httpStatus);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        $payload = [
            'ok' => false,
            'error' => [
                'code' => $error->errorCode,
                'message' => $error->getMessage(),
            ],
        ];
        if ($error->fields !== []) {
            $payload['error']['fields'] = $error->fields;
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
