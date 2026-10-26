<?php

namespace Doppar\Ripple\Support;

/** Safe config reads when the host app (or storagePath) is not booted. */
final class Config
{
    /**
     * Holds runtime config overrides keyed by dot-notation key.
     *
     * @var array<string, mixed>
     */
    private static array $overrides = [];

    /**
     * Get a config value, preferring overrides and falling back to the default when config is unavailable.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
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

    /**
     * Set a runtime override for a config key.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        self::$overrides[$key] = $value;
    }

    /**
     * Clear all runtime config overrides.
     *
     * @return void
     */
    public static function clear(): void
    {
        self::$overrides = [];
    }
}
