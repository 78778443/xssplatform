<?php
/**
 * Minimal assertion helpers for the plain-PHP test runner.
 * Each test file runs in its own PHP process; failures set a non-zero exit code.
 */

$GLOBALS['__tests_passed'] = 0;
$GLOBALS['__tests_failed'] = 0;

function test(string $name, callable $fn): void
{
    try {
        $fn();
        $GLOBALS['__tests_passed']++;
        echo "PASS  {$name}\n";
    } catch (Throwable $e) {
        $GLOBALS['__tests_failed']++;
        fwrite(STDERR, "FAIL  {$name}\n  {$e->getMessage()}\n  {$e->getFile()}:{$e->getLine()}\n");
    }
}

function __assert_fail(string $message): void
{
    throw new RuntimeException($message);
}

function assertSame(mixed $expected, mixed $actual, string $label = ''): void
{
    if ($expected !== $actual) {
        __assert_fail(
            ($label !== '' ? $label . ': ' : '')
            . 'expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true)
        );
    }
}

function assertTrue(mixed $actual, string $label = ''): void
{
    if ($actual !== true) {
        __assert_fail(($label !== '' ? $label . ': ' : '') . 'expected true, got ' . var_export($actual, true));
    }
}

function assertFalse(mixed $actual, string $label = ''): void
{
    if ($actual !== false) {
        __assert_fail(($label !== '' ? $label . ': ' : '') . 'expected false, got ' . var_export($actual, true));
    }
}

function assertThrows(string $class, callable $fn, string $label = ''): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        if ($e instanceof $class) {
            return;
        }
        __assert_fail(($label !== '' ? $label . ': ' : '') . "expected {$class}, got " . get_class($e) . ': ' . $e->getMessage());
    }
    __assert_fail(($label !== '' ? $label . ': ' : '') . "expected {$class}, nothing thrown");
}

register_shutdown_function(function (): void {
    echo sprintf("\n%d passed, %d failed\n", $GLOBALS['__tests_passed'], $GLOBALS['__tests_failed']);
    if ($GLOBALS['__tests_failed'] > 0) {
        exit(1);
    }
});
