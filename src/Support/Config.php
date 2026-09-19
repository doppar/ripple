<?php

namespace Doppar\Ripple\Support;

/** Safe config reads when the host app (or storagePath) is not booted. */
final class Config
{
    /** @var array<string, mixed> */
    private static array $overrides = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$overrides)) {
            return self::$overrides[$key];
        }

        if (! function_exists('config')) {
            return $default;
        }

        try {
            return config($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function set(string $key, mixed $value): void
    {
        self::$overrides[$key] = $value;
    }

    public static function clear(): void
    {
        self::$overrides = [];
    }
}
