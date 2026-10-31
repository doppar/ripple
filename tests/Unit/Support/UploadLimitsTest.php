<?php

namespace Doppar\Ripple\Tests\Unit\Support;

use Doppar\Ripple\Support\UploadLimits;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UploadLimitsTest extends TestCase
{
    /**
     * @return array<string, array{string, int}>
     */
    public static function sizes(): array
    {
        return [
            'megabytes' => ['8M', 8 * 1024 * 1024],
            'lowercase' => ['2m', 2 * 1024 * 1024],
            'gigabytes' => ['1G', 1024 * 1024 * 1024],
            'kilobytes' => ['512K', 512 * 1024],
            'plain bytes' => ['1048576', 1048576],
            'zero is unlimited' => ['0', 0],
            'minus one is unlimited' => ['-1', 0],
            'empty is unlimited' => ['', 0],
        ];
    }

    #[DataProvider('sizes')]
    public function test_converts_ini_sizes_to_bytes(string $ini, int $bytes): void
    {
        $this->assertSame($bytes, UploadLimits::toBytes($ini));
    }

    public function test_body_over_the_limit_is_detected(): void
    {
        $this->assertTrue(UploadLimits::exceedsPostLimit(9 * 1024 * 1024, '8M'));
        $this->assertFalse(UploadLimits::exceedsPostLimit(8 * 1024 * 1024, '8M'));
        $this->assertFalse(UploadLimits::exceedsPostLimit(100, '8M'));
    }

    public function test_an_unlimited_post_size_never_exceeds(): void
    {
        $this->assertFalse(UploadLimits::exceedsPostLimit(PHP_INT_MAX, '0'));
        $this->assertFalse(UploadLimits::exceedsPostLimit(PHP_INT_MAX, '-1'));
    }
}
