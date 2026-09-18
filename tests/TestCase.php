<?php

namespace Doppar\Ripple\Tests;

use Doppar\Ripple\Tests\Support\AppHarness;
use Phaseolies\DI\Container;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private static ?AppHarness $bootHarness = null;

    protected function setUp(): void
    {
        parent::setUp();
        self::ensureTranslator();
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
