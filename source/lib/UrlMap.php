<?php
final class UrlMap
{
    /**
     * Map a legacy page request to its SPA route. Returns null when the request
     * belongs to a receiver endpoint or an action (handled elsewhere).
     */
    public static function pageTarget(string $do, string $act, array $query): ?string
    {
        if (in_array($do, ['code', 'api', 'do', 'keepsession'], true)) {
            return null;
        }
        $act = strtolower($act);
        $id = (string) ($query['id'] ?? '');

        switch ($do) {
        case 'index':
            return '/';
        case 'login':
            return '/login';
        case 'register':
            return '/register' . (!empty($query['key']) ? '?key=' . rawurlencode((string) $query['key']) : '');
        case 'project':
            switch ($act) {
            case '':
            case 'list':
            case 'view':
                if ($act === '' || $act === 'list') {
                    return '/project' . ($id !== '' ? '' : '');
                }
                return '/project/' . rawurlencode($id)
                    . (!empty($query['domain']) ? '?domain=' . rawurlencode((string) $query['domain']) : '');
            case 'create':
                return '/project/new';
            case 'setcode':
                return '/project/' . rawurlencode($id) . '/config';
            case 'viewcode':
                return '/project/' . rawurlencode($id) . '/code';
            default:
                return null;
            }
        case 'module':
            switch ($act) {
            case '':
            case 'list':
                return '/modules';
            case 'create':
                return '/modules/new';
            case 'view':
                return '/modules/' . rawurlencode($id);
            case 'set':
                return '/modules/' . rawurlencode($id) . '/edit';
            default:
                return null;
            }
        case 'user':
            switch ($act) {
            case '':
            case 'seting':
                return '/settings';
            case 'invite':
                return '/invites';
            default:
                return null;
            }
        case 'admin_index':
            return '/admin';
        case 'admin_module':
            return '/admin/modules';
        default:
            return null;
        }
    }
}
