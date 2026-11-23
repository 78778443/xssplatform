<?php
final class Validate
{
    public static function username(string $value): bool
    {
        return (bool) preg_match('/^[\w\x{4e00}-\x{9fa5}]{2,20}$/u', $value);
    }

    public static function email(string $value): bool
    {
        return (bool) preg_match('/^(\w+\.)*?\w+@(\w+\.)+\w+$/', $value);
    }

    public static function password(string $value): bool
    {
        return (bool) preg_match('/^.{6,20}$/', $value);
    }
}
