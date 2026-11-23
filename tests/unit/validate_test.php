<?php
require __DIR__ . '/../lib.php';
require __DIR__ . '/../../source/lib/Validate.php';

test('password accepts six to twenty characters', function () {
    assertTrue(Validate::password('abcdef'));
    assertTrue(Validate::password(str_repeat('x', 20)));
});

test('password rejects shorter or longer values', function () {
    assertFalse(Validate::password('abcde'));
    assertFalse(Validate::password(str_repeat('x', 21)));
});

test('email requires local part and domain dots', function () {
    assertTrue(Validate::email('user@example.com'));
    assertFalse(Validate::email('user@localhost'));
    assertFalse(Validate::email(''));
});
