<?php

namespace Doppar\Ripple\Tests;

use Doppar\Ripple\State\StateSigner;
use Doppar\Ripple\Support\Config;
use Doppar\Ripple\Tests\Support\AppHarness;
use Phaseolies\DI\Container;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private static ?AppHarness $bootHarness = null;

    protected function setUp(): void
    {
        parent::setUp();
        Config::clear();
        self::ensureTranslator();
    }

    protected function tearDown(): void
    {
        Config::clear();
        parent::tearDown();
    }

    protected function setRippleConfig(string $key, mixed $value): void
    {
        Config::set($key, $value);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function withSignature(array $payload, StateSigner $signer): array
    {
        $state = is_array($payload['s'] ?? null) ? $payload['s'] : [];
        $id = (string) ($payload['i'] ?? '');
        $payload['x'] = $signer->sign($state, $id, (string) ($payload['n'] ?? ''));

        return $payload;
    }

    private static function ensureTranslator(): void
    {
        try {
            if (Container::getInstance()->has('translator')) {
                return;
            }
        } catch (\Throwable) {
            // No container yet.
        }

        self::$bootHarness = new AppHarness(sys_get_temp_dir() . '/ripple-phpunit-boot');
    }
}
