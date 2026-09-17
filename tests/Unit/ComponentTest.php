<?php

namespace Doppar\Ripple\Tests\Unit;

use Doppar\Ripple\Tests\Fixtures\CounterComponent;
use Doppar\Ripple\Tests\Fixtures\PaginatedComponent;
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

    public function test_get_query_string_omits_default_page(): void
    {
        $component = new PaginatedComponent();
        $this->assertNull($component->getQueryString()['page']);

        $component->page = 3;
        $this->assertSame(3, $component->getQueryString()['page']);
    }

    public function test_update_property_coerces_empty_string_to_int_zero(): void
    {
        $component = new CounterComponent();
        $component->count = 9;
        $component->updateProperty('count', '');

        $this->assertSame(0, $component->count);
    }

    public function test_update_property_coerces_numeric_string_to_int(): void
    {
        $component = new CounterComponent();
        $component->updateProperty('count', '42');

        $this->assertSame(42, $component->count);
    }
}
