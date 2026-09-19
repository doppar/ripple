<?php

namespace Doppar\Ripple\Tests\Unit\Support;

use Doppar\Ripple\Support\Config;
use Doppar\Ripple\Tests\TestCase;

final class ConfigTest extends TestCase
{
    public function test_get_returns_default_without_config_helper_binding(): void
    {
        $this->assertSame('fallback', Config::get('ripple.missing.key', 'fallback'));
    }

    public function test_override_takes_precedence(): void
    {
        Config::set('ripple.security.verify_state', 'property-updates');
        $this->assertSame('property-updates', Config::get('ripple.security.verify_state', 'strict'));
    }
}
