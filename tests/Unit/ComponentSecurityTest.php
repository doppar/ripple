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
}
