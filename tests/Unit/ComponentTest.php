<?php

namespace Doppar\Ripple\Tests\Unit;

use Doppar\Ripple\Tests\Fixtures\CounterComponent;
use Doppar\Ripple\Tests\TestCase;

final class ComponentTest extends TestCase
{
    public function test_get_state_reads_public_properties(): void
    {
        $component = new CounterComponent();
        $component->count = 3;

        $this->assertSame(['count' => 3, 'secret' => 'nope'], $component->getState());
    }

    public function test_hydrate_and_call_action(): void
    {
        $component = new CounterComponent();
        $component->hydrate(['count' => 1]);
        $component->callAction('increment');

        $this->assertSame(2, $component->count);
        $this->assertStringContainsString('Count: 2', $component->render());
    }

    public function test_call_action_rejects_unknown_method(): void
    {
        $this->expectException(\BadMethodCallException::class);

        (new CounterComponent())->callAction('missing');
    }
}
