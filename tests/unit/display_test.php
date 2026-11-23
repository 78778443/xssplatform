<?php
require __DIR__ . '/../lib.php';
require __DIR__ . '/../../source/lib/Display.php';
require __DIR__ . '/../../source/lib/Validate.php';

test('decode entities exactly one level', function () {
    assertSame('<script>', Display::decode('&lt;script&gt;'));
    assertSame('a"b', Display::decode('a&quot;b'));
    assertSame('a\\"b', Display::decode('a\\&quot;b'));
    assertSame('&lt;', Display::decode('&amp;lt;'));
});

test('username rules match legacy server rules', function () {
    assertTrue(Validate::username('ab'));
    assertTrue(Validate::username('张三'));
    assertFalse(Validate::username('a'));
    assertFalse(Validate::username('a b'));
    assertFalse(Validate::username(str_repeat('a', 21)));
});

test('email and password rules', function () {
    assertTrue(Validate::email('a.b@example.com'));
    assertFalse(Validate::email('not-an-email'));
    assertTrue(Validate::password('123456'));
    assertFalse(Validate::password('12345'));
    assertFalse(Validate::password(str_repeat('a', 21)));
});
