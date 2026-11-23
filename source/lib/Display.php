<?php
final class Display
{
    public static function decode(string $value): string
    {
        return htmlspecialchars_decode($value, ENT_QUOTES);
    }
}
