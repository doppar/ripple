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
        $sig = $signer->sign($state, 'abc');
        $this->assertTrue($signer->verify($state, 'abc', $sig));
        $this->assertFalse($signer->verify(['count' => 2], 'abc', $sig));
    }

    public function test_signer_rejects_empty_signature(): void
    {
        $signer = new StateSigner('test-key');
        $this->assertFalse($signer->verify(['a' => 1], 'id', ''));
    }

    public function test_signer_is_order_independent(): void
    {
        $signer = new StateSigner('k');
        $a = $signer->sign(['b' => 2, 'a' => 1], 'id');
        $b = $signer->sign(['a' => 1, 'b' => 2], 'id');
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
        $sig = $signer->sign(['x' => 1], 'c1');
        $this->assertTrue($signer->verify(['x' => 1], 'c1', $sig));
    }

    public function test_signer_from_config_fails_closed_in_production_without_key(): void
    {
        $this->setRippleConfig('app.env', 'production');
        $this->setRippleConfig('ripple.security.signing_key', '');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Ripple signing key is not configured');

        StateSigner::fromConfig();
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
