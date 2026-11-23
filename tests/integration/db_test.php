<?php
define('IN_OLDCMS', true);
require __DIR__ . '/../lib.php';
require __DIR__ . '/../../source/class/DB.class.php';

test('prepared mysqli layer', function () {
    $cfg = include __DIR__ . '/../config.test.php';
    $db = new DB_Mysql();
    assertTrue($db->Connect($cfg['dbHost'], $cfg['dbUser'], $cfg['dbPwd'], $cfg['database'], $cfg['charset'], $cfg['tbPrefix']), 'connect');
    assertSame('2', (string) $db->FirstValue('SELECT id FROM oc_user WHERE id=?', [2]), 'prepared select');
    assertFalse($db->FirstValue('SELECT id FROM oc_user WHERE id=?', ["' OR 1=1"]), 'injection has no rows');
    $id = $db->Insert('oc_module', ['userId' => 2, 'title' => 'db-test', 'keys' => '[]', 'setkeys' => '[]', 'code' => 'x', 'isOpen' => 0, 'addTime' => time()]);
    assertTrue($id > 0, 'insert returns id');
    $db->Update('oc_module', ['title' => 'db-test-2'], 'id=?', [$id]);
    assertSame('db-test-2', $db->FirstValue('SELECT title FROM oc_module WHERE id=?', [$id]), 'update');
    $db->Execute('DELETE FROM oc_module WHERE id=?', [$id]);
    assertFalse($db->FirstValue('SELECT title FROM oc_module WHERE id=?', [$id]), 'delete');
    assertThrows(InvalidArgumentException::class, function () use ($db) {
        $db->FirstValue('SELECT ? FROM oc_user', [1, 2]);
    });
});
