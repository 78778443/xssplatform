<?php
final class Security
{
    /**
     * Decode serialized session data without allowing object instantiation.
     * Only arrays are accepted; everything else returns $fallback.
     */
    public static function safeUnserialize(string $data, mixed $fallback = false): mixed
    {
        if ($data === '') {
            return $fallback;
        }
        try {
            $value = @unserialize($data, ['allowed_classes' => false]);
        } catch (Throwable $e) {
            return $fallback;
        }
        return is_array($value) ? $value : $fallback;
    }
}
