<?php
/**
 * Allow-list checks for outbound URLs built from user-controlled data
 * (keepsession rows, redirect targets). Only plain http/https to public
 * addresses is allowed; private and reserved ranges are rejected.
 */
final class UrlGuard
{
    public static function allowed(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return false;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }
        $ips = self::resolve((string) $parts['host']);
        if ($ips === []) {
            return false;
        }
        foreach ($ips as $ip) {
            $valid = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if ($valid === false) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return string[]
     */
    private static function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }
        $resolved = gethostbynamel($host);
        return is_array($resolved) ? $resolved : [];
    }
}
