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

    public function test_signer_from_config_falls_back(): void
    {
        $signer = StateSigner::fromConfig();
        $sig = $signer->sign(['x' => 1], 'c1');
        $this->assertTrue($signer->verify(['x' => 1], 'c1', $sig));
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
