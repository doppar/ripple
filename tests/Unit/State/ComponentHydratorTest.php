<?php

namespace Doppar\Ripple\Tests\Unit\State;

use Doppar\Ripple\State\ComponentHydrator;
use Doppar\Ripple\State\StateSigner;
use Doppar\Ripple\Tests\Fixtures\CounterComponent;
use Doppar\Ripple\Tests\Fixtures\KitchenSinkComponent;
use Doppar\Ripple\Tests\Fixtures\LazyDeskComponent;
use Doppar\Ripple\Tests\Support\AppHarness;
use Doppar\Ripple\Tests\TestCase;

final class ComponentHydratorTest extends TestCase
{
    public function test_mount_applies_params_and_calls_mount(): void
    {
        $hydrator = new ComponentHydrator(new StateSigner('k'));
        $instance = $hydrator->mount(KitchenSinkComponent::class, ['name' => 'Ada'], 'id-1');

        $this->assertSame('id-1', $instance->getId());
        $this->assertSame('Ada', $instance->name);
        $this->assertSame(1, $instance->mountCalls);
        $this->assertSame('n=Ada', $instance->summary);
    }

    public function test_hydrate_restores_state_without_mount(): void
    {
        $hydrator = new ComponentHydrator(new StateSigner('k'));
        $instance = $hydrator->hydrate(CounterComponent::class, ['count' => 8, 'secret' => 'nope'], 'x');

        $this->assertSame(8, $instance->count);
        $this->assertSame('x', $instance->getId());
    }

    public function test_lazy_detection(): void
    {
        $hydrator = new ComponentHydrator(new StateSigner('k'));
        $this->assertFalse($hydrator->isLazy(CounterComponent::class));
        $this->assertTrue($hydrator->isLazy(LazyDeskComponent::class));
        $this->assertSame('<p class="wait">Loading desk…</p>', $hydrator->lazyAttribute(LazyDeskComponent::class)?->placeholder);
        $this->assertNull($hydrator->lazyAttribute(CounterComponent::class));
    }

    public function test_mount_hydrates_query_string_before_mount(): void
    {
        $harness = new AppHarness(sys_get_temp_dir() . '/ripple-hyd-' . bin2hex(random_bytes(4)));

        try {
            $harness->bindQuery(['filter' => 'open']);
            $hydrator = new ComponentHydrator(new StateSigner('k'));
            $instance = $hydrator->mount(KitchenSinkComponent::class);

            $this->assertSame('open', $instance->filter);
        } finally {
            $harness->destroy();
        }
    }
}
