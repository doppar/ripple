<?php

namespace Doppar\Ripple\Support;

/** Safe config reads when the host app (or storagePath) is not booted. */
final class Config
{
    public static function get(string $key, mixed $default = null): mixed
    {
        if (!function_exists('config')) {
            return $default;
        }

        try {
            return config($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}
