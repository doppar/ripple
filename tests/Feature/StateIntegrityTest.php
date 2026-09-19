<?php

namespace Doppar\Ripple\Tests\Feature;

use Doppar\Ripple\ComponentManager;
use Doppar\Ripple\State\StateSigner;
use Doppar\Ripple\Tests\Fixtures\CounterComponent;
use Doppar\Ripple\Tests\TestCase;

final class StateIntegrityTest extends TestCase
{
    private ComponentManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new ComponentManager(signer: new StateSigner('integrity-key'));
    }

    public function test_action_with_forged_locked_prop_keeps_server_default_under_strict(): void
    {
        $honest = ['count' => 0, 'secret' => 'nope'];
        $forged = ['count' => 0, 'secret' => 'FORGED'];

        // Attacker signs forged state only if they have the key — without a valid
        // signature for forged state, strict mode rejects. With a valid signature
        // over forged locked values they would need the signing key (impossible).
        // Here: unsigned / wrong-sig forgery attempt fails HMAC first.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Invalid Ripple state signature');

        $this->manager->update([
            'i' => 'atk-1',
            'n' => CounterComponent::class,
            't' => 'a',
            'a' => 'increment',
            'g' => [],
            's' => $forged,
            'x' => $this->manager->signer()->sign($honest, 'atk-1'),
        ]);
    }

    public function test_action_without_hmac_fails_under_strict(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Invalid Ripple state signature');

        $this->manager->update([
            'i' => 'atk-2',
            'n' => CounterComponent::class,
            't' => 'a',
            'a' => 'increment',
            'g' => [],
            's' => ['count' => 0, 'secret' => 'FORGED'],
        ]);
    }

    public function test_action_with_valid_hmac_and_honest_state_succeeds_under_strict(): void
    {
        $result = $this->manager->update($this->withSignature([
            'i' => 'ok-1',
            'n' => CounterComponent::class,
            't' => 'a',
            'a' => 'increment',
            'g' => [],
            's' => ['count' => 2, 'secret' => 'nope'],
        ], $this->manager->signer()));

        $this->assertSame(3, $result['s']['count']);
        $this->assertSame('nope', $result['s']['secret']);
    }

    public function test_property_update_of_locked_still_rejected(): void
    {
        $state = ['count' => 0, 'secret' => 'nope'];
        $id = 'lock-u';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('locked');

        $this->manager->update($this->withSignature([
            'i' => $id,
            'n' => CounterComponent::class,
            't' => 'u',
            'p' => 'secret',
            'v' => 'hacked',
            's' => $state,
        ], $this->manager->signer()));
    }

    public function test_property_updates_mode_ignores_forged_locked_on_unsigned_action(): void
    {
        $this->setRippleConfig('ripple.security.verify_state', 'property-updates');

        $result = $this->manager->update([
            'i' => 'compat-1',
            'n' => CounterComponent::class,
            't' => 'a',
            'a' => 'increment',
            'g' => [],
            's' => ['count' => 5, 'secret' => 'FORGED'],
        ]);

        $this->assertSame(6, $result['s']['count']);
        $this->assertSame('nope', $result['s']['secret']);
    }

    public function test_property_updates_mode_still_requires_hmac_for_property_sync(): void
    {
        $this->setRippleConfig('ripple.security.verify_state', 'property-updates');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Invalid Ripple state signature');

        $this->manager->update([
            'i' => 'compat-u',
            'n' => CounterComponent::class,
            't' => 'u',
            'p' => 'count',
            'v' => 9,
            's' => ['count' => 0, 'secret' => 'nope'],
            'x' => 'bad',
        ]);
    }
}
