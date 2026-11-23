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

            $csrfActions = [
                'logout',
                'project.create', 'project.setcode.save', 'project.delete',
                'project.contents.delete', 'project.contents.deleteMany',
                'module.create', 'module.save', 'module.delete',
                'user.settings.save', 'user.invite.create', 'admin.module.audit',
            ];
            if ($installed && in_array($action, $csrfActions, true)) {
                if (!current_user()->checkToken((string) ($server['HTTP_X_CSRF_TOKEN'] ?? ''))) {
                    throw new AppError('operation_failed', 419, '操作失败');
                }
            }

            switch ($action) {
            case 'meta':
                $data = self::sessionPayload($installed, false);
                break;
            case 'session':
                if ($installed) {
                    require_once ROOT_PATH . '/source/services/AuthService.php';
                    $data = (new AuthService())->session();
                } else {
                    $data = self::sessionPayload(false, true);
                }
                break;
            case 'login':
            case 'logout':
            case 'register.check':
            case 'register.submit':
                if (!$installed) {
                    throw new AppError('not_installed', 409, '尚未安装');
                }
                require_once ROOT_PATH . '/source/services/AuthService.php';
                $auth = new AuthService();
                if ($action === 'login') {
                    $data = $auth->login($input);
                } elseif ($action === 'logout') {
                    $data = $auth->logout();
                } elseif ($action === 'register.check') {
                    $data = $auth->registerCheck($input);
                } else {
                    $data = $auth->register($input);
                }
                break;
            case 'dashboard':
            case 'project.create':
            case 'project.get':
            case 'project.setcode.get':
            case 'project.setcode.save':
            case 'project.viewcode':
            case 'project.delete':
            case 'project.contents.delete':
            case 'project.contents.deleteMany':
                if (!$installed) {
                    throw new AppError('not_installed', 409, '尚未安装');
                }
                require_once ROOT_PATH . '/source/services/ProjectService.php';
                $project = new ProjectService();
                if ($action === 'dashboard') {
                    $data = $project->dashboard();
                } elseif ($action === 'project.create') {
                    $data = $project->create($input);
                } elseif ($action === 'project.get') {
                    $data = $project->get($input);
                } elseif ($action === 'project.setcode.get') {
                    $data = $project->setcodeGet($input);
                } elseif ($action === 'project.setcode.save') {
                    $data = $project->setcodeSave($input);
                } elseif ($action === 'project.viewcode') {
                    $data = $project->viewcode($input);
                } elseif ($action === 'project.delete') {
                    $data = $project->delete($input);
                } elseif ($action === 'project.contents.delete') {
                    $data = $project->deleteContent($input);
                } else {
                    $data = $project->deleteContents($input);
                }
                break;
            case 'modules.list':
            case 'module.create':
            case 'module.get':
            case 'module.save':
            case 'module.delete':
                if (!$installed) {
                    throw new AppError('not_installed', 409, '尚未安装');
                }
                require_once ROOT_PATH . '/source/services/ModuleService.php';
                $moduleService = new ModuleService();
                if ($action === 'modules.list') {
                    $data = $moduleService->list();
                } elseif ($action === 'module.create') {
                    $data = $moduleService->create($input);
                } elseif ($action === 'module.get') {
                    $data = $moduleService->get($input);
                } elseif ($action === 'module.save') {
                    $data = $moduleService->save($input);
                } else {
                    $data = $moduleService->delete($input);
                }
                break;
            case 'user.settings.get':
            case 'user.settings.save':
            case 'user.invites':
            case 'user.invite.create':
                if (!$installed) {
                    throw new AppError('not_installed', 409, '尚未安装');
                }
                require_once ROOT_PATH . '/source/services/UserService.php';
                $userService = new UserService();
                if ($action === 'user.settings.get') {
                    $data = $userService->settingsGet();
                } elseif ($action === 'user.settings.save') {
                    $data = $userService->settingsSave($input);
                } elseif ($action === 'user.invites') {
                    $data = $userService->invites();
                } else {
                    $data = $userService->createInvite($input);
                }
                break;
            case 'admin.overview':
            case 'admin.modules':
            case 'admin.module.audit':
                if (!$installed) {
                    throw new AppError('not_installed', 409, '尚未安装');
                }
                require_once ROOT_PATH . '/source/services/AdminService.php';
                $adminService = new AdminService();
                if ($action === 'admin.overview') {
                    $data = $adminService->overview();
                } elseif ($action === 'admin.modules') {
                    $data = $adminService->modules($input);
                } else {
                    $data = $adminService->audit($input);
                }
                break;
            case 'install.status':
            case 'install.testDb':
            case 'install.submit':
                if ($installed && $action !== 'install.status') {
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
        echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
