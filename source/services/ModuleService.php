<?php
final class ModuleService
{
    private function requireUser(): User
    {
        $user = current_user();
        if ($user->userId <= 0) {
            throw new AppError('not_logged_in', 401, '未登录或已超时');
        }
        return $user;
    }

    private function visibleModule(int $id): array
    {
        $user = $this->requireUser();
        $module = db()->FirstRow('SELECT * FROM ' . Tb('module') . ' WHERE id=?', [$id]);
        if (empty($module)) {
            throw new AppError('module_not_found', 404, '模块不存在或没有权限');
        }
        $isPublicAudited = (int) $module['isOpen'] === 1 && (int) $module['isAudit'] === 1;
        if (!$user->isAdmin() && (int) $module['userId'] !== $user->userId && !$isPublicAudited) {
            throw new AppError('module_not_found', 404, '模块不存在或没有权限');
        }
        return $module;
    }

    private function decodeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return Display::decode($value);
        }
        if (is_array($value)) {
            return array_map([$this, 'decodeValue'], $value);
        }
        return $value;
    }

    public function list(): array
    {
        $user = $this->requireUser();
        $modules = db()->Dataset(
            'SELECT * FROM ' . Tb('module') . ' WHERE userId=? OR (isOpen=1 AND isAudit=1) ORDER BY id DESC',
            [$user->userId]
        );
        foreach ($modules as $k => $v) {
            $modules[$k]['title'] = Display::decode((string) $v['title']);
            $modules[$k]['description'] = Display::decode((string) $v['description']);
        }
        return ['modules' => $modules];
    }

    public function create(array $input): array
    {
        $user = $this->requireUser();
        $title = StripStr((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new AppError('title_required', 400, '模块名称不能为空');
        }
        $keys = $this->stringList($input['keys'] ?? []);
        $setkeys = $this->stringList($input['setkeys'] ?? []);
        $id = db()->Insert(Tb('module'), [
            'title' => $title,
            'description' => StripStr((string) ($input['description'] ?? '')),
            'userId' => $user->userId,
            'keys' => JsonEncode($keys),
            'setkeys' => JsonEncode($setkeys),
            'code' => StripStr((string) ($input['code'] ?? '')),
            'isOpen' => (int) ($input['isOpen'] ?? 0) === 1 ? 1 : 0,
            'addTime' => time(),
        ]);
        return ['id' => $id];
    }

    public function get(array $input): array
    {
        $module = $this->visibleModule((int) ($input['id'] ?? 0));
        $user = $this->requireUser();
        $keys = json_decode((string) $module['keys'], true);
        $setkeys = json_decode((string) $module['setkeys'], true);
        $canEdit = (int) $module['userId'] === $user->userId
            && ((int) $module['isOpen'] !== 1 || (int) $module['isAudit'] !== 1 || $user->isAdmin());
        $module['title'] = Display::decode((string) $module['title']);
        $module['description'] = Display::decode((string) $module['description']);
        $module['code'] = Display::decode((string) $module['code']);
        return [
            'module' => $module,
            'keys' => is_array($keys) ? $this->decodeValue($keys) : [],
            'setkeys' => is_array($setkeys) ? $this->decodeValue($setkeys) : [],
            'canEdit' => $canEdit,
        ];
    }

    public function save(array $input): array
    {
        $id = (int) ($input['id'] ?? 0);
        $user = $this->requireUser();
        $module = db()->FirstRow('SELECT * FROM ' . Tb('module') . ' WHERE id=? AND userId=?', [$id, $user->userId]);
        if (empty($module)) {
            throw new AppError('module_not_found', 404, '模块不存在或没有权限');
        }
        if (!$user->isAdmin() && (int) $module['isOpen'] === 1 && (int) $module['isAudit'] === 1) {
            throw new AppError('module_locked', 400, '已通过审核的公共模块不能修改');
        }
        $title = StripStr((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new AppError('title_required', 400, '模块名称不能为空');
        }
        db()->Update(Tb('module'), [
            'title' => $title,
            'description' => StripStr((string) ($input['description'] ?? '')),
            'keys' => JsonEncode($this->stringList($input['keys'] ?? [])),
            'setkeys' => JsonEncode($this->stringList($input['setkeys'] ?? [])),
            'code' => StripStr((string) ($input['code'] ?? '')),
            'isOpen' => (int) ($input['isOpen'] ?? 0) === 1 ? 1 : 0,
        ], 'id=?', [$id]);
        return [];
    }

    public function delete(array $input): array
    {
        $id = (int) ($input['id'] ?? 0);
        $user = $this->requireUser();
        $module = db()->FirstRow('SELECT * FROM ' . Tb('module') . ' WHERE id=? AND userId=?', [$id, $user->userId]);
        if (empty($module)) {
            throw new AppError('module_not_found', 404, '模块不存在或没有权限');
        }
        db()->Execute('DELETE FROM ' . Tb('module') . ' WHERE id=?', [$id]);
        return [];
    }

    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            $item = StripStr((string) $item);
            if ($item !== '') {
                $out[] = $item;
            }
        }
        return $out;
    }
}
