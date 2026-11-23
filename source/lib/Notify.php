<?php
/**
 * Notification facade. Task 12 replaces these stubs with PHPMailer 6 and curl.
 */
final class Notify
{
    public static function sendMail(string $to, string $subject, string $body): bool
    {
        return true;
    }

    public static function sendDing(string $message, string $key): bool
    {
        return true;
    }
}
