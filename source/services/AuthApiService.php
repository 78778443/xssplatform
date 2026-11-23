<?php
final class AuthApiService
{
    /**
     * Data for index.php?do=do&auth=<authCode>.
     */
    public function data(string $authCode, string $domain): array
    {
        $db = db();
        $project = $db->FirstRow('SELECT * FROM ' . Tb('project') . ' WHERE authCode=?', [$authCode]);
        if (empty($project)) {
            return [];
        }
        $sql = 'SELECT content FROM ' . Tb('project_content') . ' WHERE projectId=?';
        $params = [(int) $project['id']];
        if ($domain !== '') {
            $sql .= ' AND domain=?';
            $params[] = $domain;
        }
        $sql .= ' ORDER BY id DESC';

        $rows = $db->FirstColumn($sql, $params);
        $data = [];
        foreach ($rows as $raw) {
            $row = (array) json_decode((string) $raw, true);
            // Legacy emitted null for keys the module did not collect.
            $data[] = [
                'url' => !empty($row['opener']) ? $row['opener'] : ($row['toplocation'] ?? null),
                'cookie' => $row['cookie'] ?? null,
            ];
        }
        return $data;
    }
}
