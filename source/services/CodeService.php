<?php
final class CodeService
{
    /**
     * Render the script served by index.php?do=code (legacy bytes preserved).
     */
    public function render(int $id, string $urlKey): string
    {
        $db = db();
        $project = $db->FirstRow(
            'SELECT * FROM ' . Tb('project') . ' WHERE id=? OR urlKey=?',
            [$id, $urlKey]
        );
        if (empty($project)) {
            return '';
        }
        $moduleSetKeys = json_decode((string) $project['moduleSetKeys'], true);
        if (!is_array($moduleSetKeys)) {
            $moduleSetKeys = [];
        }

        $code = '';
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
                            $field = 'setkey_' . $module['id'] . '_' . $setkey;
                            $moduleCode = str_replace(
                                '{set.' . $setkey . '}',
                                (string) ($moduleSetKeys[$field] ?? ''),
                                $moduleCode
                            );
                        }
                    }
                }
                $code .= htmlspecialchars_decode($moduleCode, ENT_QUOTES);
            }
        }
        $code .= htmlspecialchars_decode((string) $project['code'], ENT_QUOTES);
        return $code;
    }
}
