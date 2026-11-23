<?php
require __DIR__ . '/../lib.php';
require __DIR__ . '/../../source/lib/UrlGuard.php';

test('url guard allows only public http(s) targets', function () {
    assertTrue(UrlGuard::allowed('http://93.184.216.34/path?x=1'), 'public ipv4');
    assertTrue(UrlGuard::allowed('https://93.184.216.34:8443/'), 'https with port');

    foreach ([
        '',
        'not a url',
        'file:///etc/passwd',
        'gopher://93.184.216.34/',
        'http://127.0.0.1/',
        'http://10.0.0.5/',
        'http://192.168.1.1/',
        'http://172.16.0.1/',
        'http://169.254.169.254/latest/meta-data/',
        'http://localhost/',
        'http://[::1]/',
    ] as $url) {
        assertFalse(UrlGuard::allowed($url), 'rejected: ' . $url);
    }
});
