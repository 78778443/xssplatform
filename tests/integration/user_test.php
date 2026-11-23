<?php
require __DIR__ . '/../lib.php';
putenv('XSSP_CONFIG=' . realpath(__DIR__ . '/../config.test.php'));
require __DIR__ . '/../../source/bootstrap.php';

test('safe deserialization only accepts arrays', function () {
    assertFalse(Security::safeUnserialize('O:8:"stdClass":0:{}'), 'no object instantiation');
    assertSame(['a' => 1], Security::safeUnserialize(serialize(['a' => 1])));
});

test('legacy md5 user migrates on login and gets a hardened session', function () {
    $db = db();
    $db->Execute('DELETE FROM oc_session WHERE userId IN (SELECT id FROM oc_user WHERE userName=?)', ['legacy']);
    $db->Execute('DELETE FROM oc_user WHERE userName=?', ['legacy']);
    $db->Insert('oc_user', [
        'adminLevel' => 0,
        'userName' => 'legacy',
        'userPwd' => md5('OldCMS|' . 'secret123'),
        'email' => 'legacy@example.com',
        'addTime' => time(),
    ]);

    $u = new User($db);
    assertTrue($u->login('legacy', 'secret123', 0), 'legacy md5 login');
    $hash = $db->FirstValue('SELECT userPwd FROM oc_user WHERE userName=?', ['legacy']);
    assertTrue(str_starts_with((string) $hash, '$2y$'), 'legacy hash migrated');
    assertTrue(password_verify('secret123', (string) $hash));

    $row = $db->FirstRow('SELECT ocKey, token, data FROM oc_session WHERE userId=?', [$u->userId]);
    assertSame(48, strlen((string) $row['ocKey']), 'ocKey length');
    assertSame(32, strlen((string) $row['token']), 'token length');
    assertTrue($u->checkToken((string) $row['token']));
    assertFalse($u->checkToken('wrong'));
});

test('old session rows remain readable', function () {
    $db = db();
    $oldKey = md5('old-session');
    $oldData = serialize(['userId' => 2, 'adminLevel' => 1, 'userName' => 'admin']);
    $db->Execute('DELETE FROM oc_session WHERE ocKey=?', [$oldKey]);
    $db->Insert('oc_session', [
        'userId' => 2,
        'ocKey' => $oldKey,
        'token' => md5('t'),
        'ip' => '127.0.0.1',
        'data' => $oldData,
        'expires' => time() + 3600,
        'updateTime' => time(),
        'addTime' => time(),
    ]);
    $_COOKIE['ocKey'] = $oldKey;
    $oldUser = new User($db);
    assertSame(2, $oldUser->userId, 'old session readable');
    assertSame('admin', $oldUser->userName, 'old session username');
    $_COOKIE['ocKey'] = '';
});
