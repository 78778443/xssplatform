<?php
require __DIR__ . '/../lib.php';
require __DIR__ . '/../../source/lib/UrlMap.php';

test('legacy page urls map to spa routes', function () {
    assertSame('/login', UrlMap::pageTarget('login', '', []));
    assertSame('/register?key=abc', UrlMap::pageTarget('register', '', ['key' => 'abc']));
    assertSame('/project/5', UrlMap::pageTarget('project', 'view', ['id' => '5']));
    assertSame('/project/5?domain=x.com', UrlMap::pageTarget('project', 'view', ['id' => '5', 'domain' => 'x.com']));
    assertSame('/project/new', UrlMap::pageTarget('project', 'create', []));
    assertSame('/project/5/config', UrlMap::pageTarget('project', 'setcode', ['id' => '5']));
    assertSame('/project/5/code', UrlMap::pageTarget('project', 'viewcode', ['id' => '5']));
    assertSame('/modules', UrlMap::pageTarget('module', '', []));
    assertSame('/modules/new', UrlMap::pageTarget('module', 'create', []));
    assertSame('/modules/7', UrlMap::pageTarget('module', 'view', ['id' => '7']));
    assertSame('/modules/7/edit', UrlMap::pageTarget('module', 'set', ['id' => '7']));
    assertSame('/settings', UrlMap::pageTarget('user', 'seting', []));
    assertSame('/invites', UrlMap::pageTarget('user', 'invite', []));
    assertSame('/admin/modules', UrlMap::pageTarget('admin_module', '', []));
    assertSame(null, UrlMap::pageTarget('code', '', []));
});
