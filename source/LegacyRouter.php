<?php
final class LegacyRouter
{
    public static function route(string $do, array $get, array $post, array $server): void
    {
        $act = strtolower((string) ($get['act'] ?? ''));
        $input = array_merge($get, $post);

        if (self::isAction($do, $act)) {
            self::loadServices($do);
            require_once ROOT_PATH . '/source/lib/AppError.php';
        }

        if (!self::isAction($do, $act)) {
            $target = UrlMap::pageTarget($do, $act, $get);
            if ($target !== null) {
                self::redirect($target);
            }
            self::notFound();
            return;
        }

        try {
            self::guardWrite($do, $act, $server);
            $target = self::runAction($do, $act, $input);
            self::redirect($target);
        } catch (AppError $e) {
            self::redirectBack($do, $act, $input, $e->errorCode);
        } catch (Throwable $e) {
            error_log('legacy action error: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Internal Server Error';
        }
    }

    /**
     * Old forms are kept working, but writes now require a same-origin request,
     * a POST body and (where the legacy form had one) a CSRF token.
     */
    private static function guardWrite(string $do, string $act, array $server): void
    {
        if ($do === 'register' && $act === 'checkue') {
            return;
        }
        if (!self::sameOrigin($server)) {
            throw new AppError('no_permission', 403, '没有操作权限');
        }
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'POST') {
            throw new AppError('method_not_allowed', 405, '请使用 POST 提交');
        }
        // Unauthenticated login/registration cannot carry a session token yet.
        // Every authenticated legacy write must present the old form token.
        if ($do === 'project' || $do === 'module' || $do === 'user' || $do === 'admin_module'
            || ($do === 'login' && $act === 'logout')) {
            self::requireToken();
        }
    }

    public static function sameOrigin(array $server): bool
    {
        $origin = (string) ($server['HTTP_ORIGIN'] ?? '');
        $referer = (string) ($server['HTTP_REFERER'] ?? '');
        $candidate = $origin !== '' ? $origin : $referer;
        if ($candidate === '') {
            return true;
        }
        $host = parse_url($candidate, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }
        $expectedHosts = [];
        if (defined('URL_ROOT') && URL_ROOT !== '') {
            $rootHost = parse_url(URL_ROOT, PHP_URL_HOST);
            if (is_string($rootHost) && $rootHost !== '') {
                $expectedHosts[] = $rootHost;
            }
        }
        $requestHost = (string) ($server['HTTP_HOST'] ?? '');
        if ($requestHost !== '') {
            if ($requestHost[0] === '[') {
                $end = strpos($requestHost, ']');
                $requestHost = $end === false ? $requestHost : substr($requestHost, 0, $end + 1);
            } else {
                $requestHost = explode(':', $requestHost)[0];
            }
            $expectedHosts[] = $requestHost;
        }
        foreach ($expectedHosts as $expected) {
            if (hash_equals(strtolower($expected), strtolower($host))) {
                return true;
            }
        }
        return false;
    }

    private static function isAction(string $do, string $act): bool
    {
        $actions = [
            'login' => ['submit', 'logout'],
            'register' => ['submit', 'checkue'],
            'project' => ['create_submit', 'setcode_submit', 'delete', 'delcontent', 'delcontents'],
            'module' => ['create_submit', 'set_submit', 'delete'],
            'user' => ['submit', 'createinvite'],
            'admin_module' => ['audit'],
        ];
        return isset($actions[$do]) && in_array($act, $actions[$do], true);
    }

    private static function token(): string
    {
        return (string) (Val('token', 'POST') ?: Val('token', 'GET'));
    }

    private static function requireToken(): void
    {
        if (!current_user()->checkToken(self::token())) {
            throw new AppError('operation_failed', 419, '操作失败');
        }
    }

    private static function runAction(string $do, string $act, array $input): string
    {
        switch ($do) {
        case 'login':
            if ($act === 'logout') {
                (new AuthService())->logout();
                return '/';
            }
            $result = (new AuthService())->login($input);
            return '/';

        case 'register':
            if ($act === 'checkue') {
                $result = (new AuthService())->registerCheck($input);
                echo $result['userExisted'] . '|' . $result['emailExisted'] . '|' . $result['keyError'];
                exit;
            }
            (new AuthService())->register($input);
            return '/?ok=registered';

        case 'project':
            if ($act === 'create_submit') {
                $result = (new ProjectService())->create($input);
                return '/project/' . $result['id'] . '/config?created=1';
            }
            if ($act === 'setcode_submit') {
                $id = (int) ($input['id'] ?? 0);
                $ty = (string) ($input['ty'] ?? '');
                self::requireToken();
                (new ProjectService())->setcodeSave($input);
                return $ty === 'create'
                    ? '/project/' . $id . '/code'
                    : '/project/' . $id . '?ok=saved';
            }
            if ($act === 'delete') {
                self::requireToken();
                (new ProjectService())->delete($input);
                return '/?ok=deleted';
            }
            if ($act === 'delcontent') {
                self::requireToken();
                (new ProjectService())->deleteContent($input);
                echo 1;
                exit;
            }
            if ($act === 'delcontents') {
                self::requireToken();
                (new ProjectService())->deleteContents($input);
                echo 1;
                exit;
            }
            break;

        case 'module':
            if ($act === 'create_submit') {
                $result = (new ModuleService())->create($input);
                return '/modules/' . $result['id'] . '?ok=created';
            }
            if ($act === 'set_submit') {
                self::requireToken();
                (new ModuleService())->save($input);
                return '/modules/' . (int) ($input['id'] ?? 0) . '?ok=saved';
            }
            if ($act === 'delete') {
                self::requireToken();
                (new ModuleService())->delete($input);
                return '/modules?ok=deleted';
            }
            break;

        case 'user':
            if ($act === 'submit') {
                (new UserService())->settingsSave($input);
                return '/settings?ok=saved';
            }
            if ($act === 'createinvite') {
                (new UserService())->createInvite($input);
                return '/invites?ok=created';
            }
            break;

        case 'admin_module':
            if ($act === 'audit') {
                (new AdminService())->audit($input);
                return '/admin/modules?ok=audited';
            }
            break;
        }

        throw new AppError('not_found', 404, '操作不存在');
    }

    private static function loadServices(string $do): void
    {
        switch ($do) {
        case 'login':
        case 'register':
            require_once ROOT_PATH . '/source/services/AuthService.php';
            break;
        case 'project':
            require_once ROOT_PATH . '/source/services/ProjectService.php';
            break;
        case 'module':
            require_once ROOT_PATH . '/source/services/ModuleService.php';
            break;
        case 'user':
            require_once ROOT_PATH . '/source/services/UserService.php';
            break;
        case 'admin_module':
            require_once ROOT_PATH . '/source/services/AdminService.php';
            break;
        }
    }

    private static function redirect(string $target): void
    {
        header('Location: ' . $target);
        exit;
    }

    private static function redirectBack(string $do, string $act, array $input, string $code): void
    {
        $target = UrlMap::pageTarget($do, $act, $input);
        if ($target === null) {
            $target = '/';
        }
        $sep = str_contains($target, '?') ? '&' : '?';
        self::redirect($target . $sep . 'error=' . rawurlencode($code));
    }

    private static function notFound(): void
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Not Found';
        exit;
    }
}
