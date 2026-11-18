<?php

namespace Doppar\Ripple\Tests\Unit;

use Doppar\Ripple\State\StateSigner;
use Doppar\Ripple\Tests\Fixtures\CounterComponent;
use Doppar\Ripple\Tests\TestCase;

final class ComponentSecurityTest extends TestCase
{
    public function test_invokable_required(): void
    {
        $c = new CounterComponent();
        $this->expectException(\BadMethodCallException::class);
        $c->callAction('render');
    }

    public function test_locked_property_rejects_client_update(): void
    {
        $c = new CounterComponent();
        $this->expectException(\InvalidArgumentException::class);
        $c->updateProperty('secret', 'hacked');
    }

    public function test_hydrate_ignores_locked_mutation_by_default(): void
    {
        $c = new CounterComponent();
        $c->hydrate(['secret' => 'FORGED', 'count' => 1]);

        $this->assertSame('nope', $c->secret);
        $this->assertSame(1, $c->count);
    }

    public function test_hydrate_applies_locked_when_explicitly_allowed(): void
    {
        $c = new CounterComponent();
        $c->hydrate(['secret' => 'from-signed-state', 'count' => 2], allowLocked: true);

        $this->assertSame('from-signed-state', $c->secret);
        $this->assertSame(2, $c->count);
    }

    public function test_signer_round_trip(): void
    {
        $signer = new StateSigner('test-key');
        $state = ['count' => 1];
        $sig = $signer->sign($state, 'abc', CounterComponent::class);
        $this->assertTrue($signer->verify($state, 'abc', CounterComponent::class, $sig));
        $this->assertFalse($signer->verify(['count' => 2], 'abc', CounterComponent::class, $sig));
    }

    public function test_signer_rejects_empty_signature(): void
    {
        $signer = new StateSigner('test-key');
        $this->assertFalse($signer->verify(['a' => 1], 'id', CounterComponent::class, ''));
    }

    public function test_signer_is_order_independent(): void
    {
        $signer = new StateSigner('k');
        $a = $signer->sign(['b' => 2, 'a' => 1], 'id', CounterComponent::class);
        $b = $signer->sign(['a' => 1, 'b' => 2], 'id', CounterComponent::class);
        $this->assertSame($a, $b);
        $this->assertSame(
            $signer->fingerprint(['b' => 2, 'a' => 1]),
            $signer->fingerprint(['a' => 1, 'b' => 2]),
        );
    }

    public function test_signer_from_config_falls_back_outside_production(): void
    {
        $this->setRippleConfig('app.env', 'local');
        $signer = StateSigner::fromConfig();
        $sig = $signer->sign(['x' => 1], 'c1', CounterComponent::class);
        $this->assertTrue($signer->verify(['x' => 1], 'c1', CounterComponent::class, $sig));
    }

    public function test_signer_from_config_fails_closed_in_production_without_key(): void
    {
        $this->setRippleConfig('app.env', 'production');
        $this->setRippleConfig('ripple.security.signing_key', '');
        $this->setRippleConfig('app.key', null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Ripple signing key is not configured');

        StateSigner::fromConfig();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function deployedEnvironments(): array
    {
        return [
            'staging' => ['staging'],
            'live' => ['live'],
            'prod-eu' => ['prod-eu'],
            'uppercase production' => ['PRODUCTION'],
            'empty string' => [''],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('deployedEnvironments')]
    public function test_signer_from_config_refuses_dev_key_in_any_non_development_env(string $env): void
    {
        $this->setRippleConfig('app.env', $env);
        $this->setRippleConfig('ripple.security.signing_key', null);
        $this->setRippleConfig('app.key', null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Ripple signing key is not configured');

        StateSigner::fromConfig();
    }

    public function test_signer_from_config_refuses_dev_key_when_env_is_unset(): void
    {
        if (function_exists('env') && env('APP_ENV') !== null) {
            $this->markTestSkipped('APP_ENV is set in the process environment.');
        }

        $this->setRippleConfig('app.env', null);
        $this->setRippleConfig('ripple.security.signing_key', null);
        $this->setRippleConfig('app.key', null);

        $this->expectException(\RuntimeException::class);

        StateSigner::fromConfig();
    }

    public function test_signer_from_config_refuses_explicit_dev_key_outside_development(): void
    {
        $this->setRippleConfig('app.env', 'staging');
        $this->setRippleConfig('ripple.security.signing_key', StateSigner::DEV_KEY);

        $this->expectException(\RuntimeException::class);

        StateSigner::fromConfig();
    }

    public function test_signer_from_config_falls_back_in_testing_env(): void
    {
        $this->setRippleConfig('app.env', 'Testing');
        $this->setRippleConfig('ripple.security.signing_key', null);
        $this->setRippleConfig('app.key', null);

        $signer = StateSigner::fromConfig();
        $forged = (new StateSigner(StateSigner::DEV_KEY))->sign(['x' => 1], 'c1', CounterComponent::class);

        $this->assertTrue($signer->verify(['x' => 1], 'c1', CounterComponent::class, $forged));
    }

    public function test_signer_from_config_rejects_short_key_outside_development(): void
    {
        $this->setRippleConfig('app.env', 'production');
        $this->setRippleConfig('ripple.security.signing_key', 'short-key');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('too short');

        StateSigner::fromConfig();
    }

    public function test_signer_from_config_uses_configured_key_in_production(): void
    {
        $key = str_repeat('k', StateSigner::MIN_KEY_LENGTH);
        $this->setRippleConfig('app.env', 'production');
        $this->setRippleConfig('ripple.security.signing_key', $key);

        $signer = StateSigner::fromConfig();
        $sig = (new StateSigner($key))->sign(['x' => 1], 'c1', CounterComponent::class);
        $devSig = (new StateSigner(StateSigner::DEV_KEY))->sign(['x' => 1], 'c1', CounterComponent::class);

        $this->assertTrue($signer->verify(['x' => 1], 'c1', CounterComponent::class, $sig));
        $this->assertFalse($signer->verify(['x' => 1], 'c1', CounterComponent::class, $devSig));
    }

    public function test_empty_ripple_key_falls_through_to_app_key(): void
    {
        $appKey = 'base64:' . base64_encode(str_repeat('a', 32));
        $this->setRippleConfig('app.env', 'production');
        $this->setRippleConfig('ripple.security.signing_key', '   ');
        $this->setRippleConfig('app.key', $appKey);

        $signer = StateSigner::fromConfig();
        $sig = (new StateSigner($appKey))->sign(['x' => 1], 'c1', CounterComponent::class);

        $this->assertTrue($signer->verify(['x' => 1], 'c1', CounterComponent::class, $sig));
    }

    public function test_fingerprint_changes_with_state(): void
    {
        $signer = new StateSigner('k');
        $this->assertNotSame(
            $signer->fingerprint(['count' => 1]),
            $signer->fingerprint(['count' => 2]),
        );
    }
}
