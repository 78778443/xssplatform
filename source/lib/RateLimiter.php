<?php
/**
 * Database-backed fixed-window rate limiter.
 *
 * Used to slow down credential guessing and registration abuse. Buckets are
 * hashed so the table never stores raw usernames, e-mail addresses or IPs.
 * Failures are logged and fail open: a broken limiter must not lock every
 * user out of the platform.
 */
final class RateLimiter
{
    private static bool $tableReady = false;

    public static function bucket(string $scope, string ...$parts): string
    {
        return $scope . ':' . hash('sha256', $scope . '|' . implode('|', $parts));
    }

    /**
     * Seconds until the bucket is usable again; 0 when it is not blocked.
     */
    public static function retryAfter(string $bucket): int
    {
        try {
            self::ensureTable();
            $row = db()->FirstRow(
                'SELECT blockedUntil FROM ' . Tb('rate_limit') . ' WHERE bucketKey=?',
                [$bucket]
            );
            if (empty($row)) {
                return 0;
            }
            return max(0, (int) $row['blockedUntil'] - time());
        } catch (Throwable $e) {
            error_log('rate limiter check failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Record one attempt. Returns the block duration in seconds when the
     * attempt pushes the bucket over its limit, otherwise 0.
     */
    public static function hit(string $bucket, int $limit, int $window, int $block): int
    {
        try {
            self::ensureTable();
            $db = db();
            $now = time();
            $row = $db->FirstRow(
                'SELECT attempts,windowStart,blockedUntil FROM ' . Tb('rate_limit') . ' WHERE bucketKey=?',
                [$bucket]
            );
            $attempts = 1;
            $windowStart = $now;
            $blockedUntil = 0;
            if (!empty($row)) {
                $attempts = (int) $row['attempts'];
                $windowStart = (int) $row['windowStart'];
                $blockedUntil = (int) $row['blockedUntil'];
                if ($blockedUntil > $now) {
                    return $blockedUntil - $now;
                }
                if ($now - $windowStart >= $window) {
                    $attempts = 0;
                    $windowStart = $now;
                }
                $attempts++;
                if ($attempts >= $limit) {
                    $blockedUntil = $now + $block;
                }
                $db->Update(Tb('rate_limit'), [
                    'attempts' => $attempts,
                    'windowStart' => $windowStart,
                    'blockedUntil' => $blockedUntil,
                    'updateTime' => $now,
                ], 'bucketKey=?', [$bucket]);
            } else {
                $db->Insert(Tb('rate_limit'), [
                    'bucketKey' => $bucket,
                    'attempts' => $attempts,
                    'windowStart' => $windowStart,
                    'blockedUntil' => 0,
                    'updateTime' => $now,
                ]);
            }
            self::maybeCleanup();
            return max(0, $blockedUntil - $now);
        } catch (Throwable $e) {
            error_log('rate limiter update failed: ' . $e->getMessage());
            return 0;
        }
    }

    public static function clear(string $bucket): void
    {
        try {
            self::ensureTable();
            db()->Execute('DELETE FROM ' . Tb('rate_limit') . ' WHERE bucketKey=?', [$bucket]);
        } catch (Throwable $e) {
            error_log('rate limiter clear failed: ' . $e->getMessage());
        }
    }

    private static function ensureTable(): void
    {
        if (self::$tableReady) {
            return;
        }
        self::$tableReady = true;
        $table = Tb('rate_limit');
        $exists = db()->FirstValue(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',
            [$table]
        );
        if ((int) $exists === 0) {
            db()->Execute(
                'CREATE TABLE IF NOT EXISTS `' . $table . '` ('
                . '`bucketKey` varchar(191) NOT NULL,'
                . '`attempts` int(11) NOT NULL DEFAULT 0,'
                . '`windowStart` int(11) NOT NULL DEFAULT 0,'
                . '`blockedUntil` int(11) NOT NULL DEFAULT 0,'
                . '`updateTime` int(11) NOT NULL DEFAULT 0,'
                . 'PRIMARY KEY (`bucketKey`)'
                . ') ENGINE=MyISAM DEFAULT CHARSET=utf8'
            );
        }
    }

    private static function maybeCleanup(): void
    {
        if (random_int(1, 100) !== 1) {
            return;
        }
        try {
            db()->Execute(
                'DELETE FROM ' . Tb('rate_limit') . ' WHERE updateTime<?',
                [time() - 86400]
            );
        } catch (Throwable $e) {
            error_log('rate limiter cleanup failed: ' . $e->getMessage());
        }
    }
}
