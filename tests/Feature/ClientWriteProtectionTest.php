<?php

namespace Doppar\Ripple\Tests\Feature;

use Doppar\Ripple\ComponentManager;
use Doppar\Ripple\State\StateSigner;
use Doppar\Ripple\Tests\Fixtures\CounterComponent;
use Doppar\Ripple\Tests\Fixtures\SecureComponent;
use Doppar\Ripple\Tests\TestCase;

final class ClientWriteProtectionTest extends TestCase
{
    private ComponentManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new ComponentManager(signer: new StateSigner('write-key'));
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function action(array $extra): array
    {
        $state = ['userId' => 1, 'isAdmin' => false, 'note' => ''];

        return $this->manager->update(array_merge([
            'i' => 'sec-1',
            'n' => SecureComponent::class,
            't' => 'a',
            'a' => 'save',
            'g' => [],
            's' => $state,
            'x' => $this->manager->signer()->sign($state, 'sec-1', SecureComponent::class),
        ], $extra));
    }

    public function test_bound_values_cannot_overwrite_the_locked_list(): void
    {
        $result = $this->action(['m' => ['locked' => [], 'isAdmin' => true, 'userId' => 2, 'note' => 'hi']]);

        $this->assertFalse($result['s']['isAdmin']);
        $this->assertSame(1, $result['s']['userId']);
        $this->assertSame('hi', $result['s']['note']);
    }

    public function test_bound_values_cannot_expose_protected_properties(): void
    {
        $result = $this->action(['m' => ['tracked' => ['userId', 'isAdmin', 'note', 'apiToken']]]);

        $this->assertArrayNotHasKey('apiToken', $result['s']);
    }

    public function test_bound_values_cannot_register_listeners_for_non_invokable_methods(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessageIsOrContains('is not invokable');

        $this->action([
            'm' => ['eventListeners' => ['z' => ['promote']]],
            'a' => 'promote',
            'ev' => 'z',
        ]);
    }

    public function test_property_update_cannot_target_protected_properties(): void
    {
        $state = ['userId' => 1, 'isAdmin' => false, 'note' => ''];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('cannot be updated from the client');

        $this->manager->update([
            'i' => 'sec-2',
            'n' => SecureComponent::class,
            't' => 'u',
            'p' => 'locked',
            'v' => [],
            's' => $state,
            'x' => $this->manager->signer()->sign($state, 'sec-2', SecureComponent::class),
        ]);
    }

    public function test_state_signed_for_one_component_is_rejected_for_another(): void
    {
        // A snapshot from a component where userId is writable must not unlock it elsewhere.
        $state = ['userId' => 2, 'isAdmin' => true];
        $signature = $this->manager->signer()->sign($state, 'sec-3', CounterComponent::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Invalid Ripple state signature');

        $this->manager->update([
            'i' => 'sec-3',
            'n' => SecureComponent::class,
            't' => 'a',
            'a' => 'save',
            'g' => [],
            's' => $state,
            'x' => $signature,
        ]);
    }

    public function test_signature_survives_a_leading_backslash_in_the_class(): void
    {
        $signer = $this->manager->signer();
        $state = ['userId' => 1];

        $this->assertSame(
            $signer->sign($state, 'id', SecureComponent::class),
            $signer->sign($state, 'id', '\\' . SecureComponent::class),
        );
    }
}
