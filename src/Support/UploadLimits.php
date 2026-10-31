<?php

namespace Doppar\Ripple\Support;

final class UploadLimits
{
    /**
     * Convert a php.ini size such as "8M" or "1G" to bytes.
     *
     * @param string $value
     * @return int Bytes. 0 means "no limit" (post_max_size accepts 0 and -1 for that).
     */
    public static function toBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '0' || $value === '-1') {
            return 0;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /**
     * Determine whether a request body is larger than PHP's post_max_size.
     *
     * PHP throws the whole body away in that case, so the controller sees no form fields and no
     * files, which looks exactly like a request that forgot its parameters.
     *
     * @param int $contentLength
     * @param string|null $postMaxSize The ini value. Defaults to the running post_max_size.
     * @return bool
     */
    public static function exceedsPostLimit(int $contentLength, ?string $postMaxSize = null): bool
    {
        $limit = self::toBytes($postMaxSize ?? (string) ini_get('post_max_size'));

        return $limit > 0 && $contentLength > $limit;
    }
}
