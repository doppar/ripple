<?php

namespace Doppar\Ripple\Tests\Feature;

use Doppar\Ripple\ComponentManager;
use Doppar\Ripple\Delta\Op;
use Doppar\Ripple\Tests\Fixtures\CounterComponent;
use Doppar\Ripple\Tests\TestCase;

final class ComponentManagerTest extends TestCase
{
    private ComponentManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new ComponentManager();
    }

    public function test_mount_wraps_component_with_ripple_root(): void
    {
        $html = $this->manager->mount(CounterComponent::class);

        $this->assertStringContainsString('data-ripple', $html);
        $this->assertStringContainsString('data-ripple-id=', $html);
        $this->assertStringContainsString('data-ripple-name="' . CounterComponent::class . '"', $html);
        $this->assertStringContainsString('Count: 0', $html);
        $this->assertStringContainsString('ripple-call="increment"', $html);
    }

    public function test_update_returns_short_wire_payload_with_delta(): void
    {
        $result = $this->manager->update([
            'i' => 'abc',
            'n' => CounterComponent::class,
            'a' => 'increment',
            'g' => [],
            's' => ['count' => 0],
        ]);

        $this->assertSame('abc', $result['i']);
        $this->assertSame(CounterComponent::class, $result['n']);
        $this->assertSame(1, $result['s']['count']);
        $this->assertNotEmpty($result['d']);

        $setText = array_values(array_filter(
            $result['d'],
            static fn (array $o) => ($o['o'] ?? '') === Op::SET_TEXT
        ));
        $this->assertNotEmpty($setText);
        $this->assertSame('Count: 1', $setText[0]['v'] ?? null);
    }

    public function test_register_alias_resolves_component(): void
    {
        $this->manager->register('counter', CounterComponent::class);

        $html = $this->manager->mount('counter');

        $this->assertStringContainsString('Count: 0', $html);
    }

    public function test_update_requires_component_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->manager->update(['i' => 'x', 's' => []]);
    }
}
