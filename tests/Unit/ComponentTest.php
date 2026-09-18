<?php

namespace Doppar\Ripple\Tests\Unit;

use Doppar\Ripple\Exceptions\RedirectException;
use Doppar\Ripple\Exceptions\ValidationException;
use Doppar\Ripple\Tests\Fixtures\ArgComponent;
use Doppar\Ripple\Tests\Fixtures\CounterComponent;
use Doppar\Ripple\Tests\Fixtures\FormComponent;
use Doppar\Ripple\Tests\Fixtures\KitchenSinkComponent;
use Doppar\Ripple\Tests\Fixtures\PaginatedComponent;
use Doppar\Ripple\Tests\Support\AppHarness;
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

    public function test_call_action_null_is_noop(): void
    {
        $c = new CounterComponent();
        $this->assertNull($c->callAction(null));
        $this->assertNull($c->callAction(''));
    }

    public function test_call_action_rejects_private_method(): void
    {
        $this->expectException(\BadMethodCallException::class);
        (new ArgComponent())->callAction('secretBump');
    }

    public function test_call_action_with_args(): void
    {
        $c = new ArgComponent();
        $c->callAction('bump', [5]);
        $c->callAction('rename', ['hello']);

        $this->assertSame(5, $c->n);
        $this->assertSame('hello', $c->label);
    }

    public function test_get_query_string_omits_default_page(): void
    {
        $component = new PaginatedComponent();
        $this->assertNull($component->getQueryString()['page']);

        $component->page = 3;
        $this->assertSame(3, $component->getQueryString()['page']);
    }

    public function test_query_string_keep_and_alias(): void
    {
        $c = new KitchenSinkComponent();
        $qs = $c->getQueryString();

        $this->assertNull($qs['filter']);
        $this->assertSame('home', $qs['tab']);

        $c->filter = 'open';
        $c->tab = 'settings';
        $qs = $c->getQueryString();
        $this->assertSame('open', $qs['filter']);
        $this->assertSame('settings', $qs['tab']);
    }

    public function test_hydrate_from_query_string(): void
    {
        $harness = new AppHarness(sys_get_temp_dir() . '/ripple-qs-' . bin2hex(random_bytes(4)));

        try {
            $harness->bindQuery(['page' => '4', 'filter' => 'open', 'tab' => 'billing']);
            $c = new KitchenSinkComponent();
            $c->hydrateFromQueryString();

            $this->assertSame('open', $c->filter);
            $this->assertSame('billing', $c->tab);
        } finally {
            $harness->destroy();
        }
    }

    public function test_paginated_hydrate_page_from_query(): void
    {
        $harness = new AppHarness(sys_get_temp_dir() . '/ripple-page-' . bin2hex(random_bytes(4)));

        try {
            $harness->bindQuery(['page' => '9']);
            $c = new PaginatedComponent();
            $c->hydrateFromQueryString();
            $this->assertSame(9, $c->page);
        } finally {
            $harness->destroy();
        }
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

    public function test_update_property_coerces_bool_float_array(): void
    {
        $c = new KitchenSinkComponent();
        $c->updateProperty('active', '1');
        $c->updateProperty('price', '12.5');
        $c->updateProperty('tags', 7);
        $c->updateProperty('active', '');
        $c->updateProperty('price', '');
        $c->updateProperty('tags', null);

        $this->assertFalse($c->active);
        $this->assertSame(0.0, $c->price);
        $this->assertSame([], $c->tags);

        $c->updateProperty('active', 'true');
        $c->updateProperty('price', '3.25');
        $c->updateProperty('tags', [1, 2]);

        $this->assertTrue($c->active);
        $this->assertSame(3.25, $c->price);
        $this->assertSame([1, 2], $c->tags);
    }

    public function test_locked_and_computed_reject_client_update(): void
    {
        $c = new KitchenSinkComponent();

        try {
            $c->updateProperty('token', 'x');
            $this->fail('expected locked rejection');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('locked', $e->getMessage());
        }

        try {
            $c->updateProperty('summary', 'x');
            $this->fail('expected computed rejection');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('cannot be updated', $e->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        $c->updateProperty('missing', 'x');
    }

    public function test_computed_excluded_from_state_and_hydrate(): void
    {
        $c = new KitchenSinkComponent();
        $c->summary = 'should-not-wire';
        $state = $c->getState();

        $this->assertArrayNotHasKey('summary', $state);
        $this->assertArrayHasKey('token', $state);

        $c->hydrate(['summary' => 'hack', 'name' => 'Ann']);
        $this->assertSame('should-not-wire', $c->summary);
        $this->assertSame('Ann', $c->name);
    }

    public function test_view_data_includes_errors(): void
    {
        $c = new KitchenSinkComponent();
        $c->name = 'Jo';
        $c->setErrorBag(['name' => ['bad']]);

        $data = $c->viewData();
        $this->assertSame('Jo', $data['name']);
        $this->assertSame(['name' => ['bad']], $data['errors']);
    }

    public function test_validate_required_min_and_custom_messages(): void
    {
        $c = new KitchenSinkComponent();

        try {
            $c->callAction('save');
            $this->fail('expected validation');
        } catch (ValidationException $e) {
            $this->assertSame('Name please.', $e->errors()['name'][0] ?? null);
        }

        $c->updateProperty('name', 'A');
        $this->assertNotEmpty($c->getErrorBag()['name'] ?? []);

        $c->name = 'Al';
        $c->callAction('save');
        $this->assertSame([], $c->getErrorBag());
        $this->assertSame('saved:Al', $c->summary);
    }

    public function test_validate_email_rule(): void
    {
        $c = new FormComponent();
        $c->email = 'not-an-email';

        try {
            $c->callAction('save');
            $this->fail('expected email validation');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
        }
    }

    public function test_update_property_clears_field_error_then_revalidates(): void
    {
        $c = new FormComponent();
        $c->setErrorBag(['email' => ['old'], 'note' => ['keep']]);
        $c->updateProperty('email', 'ok@example.com');

        $bag = $c->getErrorBag();
        $this->assertArrayNotHasKey('email', $bag);
        $this->assertSame(['keep'], $bag['note'] ?? null);
    }

    public function test_redirect_throws(): void
    {
        $c = new KitchenSinkComponent();

        try {
            $c->callAction('leave');
            $this->fail('expected redirect');
        } catch (RedirectException $e) {
            $this->assertSame('/elsewhere', $e->redirect()['u']);
            $this->assertFalse($e->redirect()['spa']);
        }
    }

    public function test_redirect_defaults_to_spa(): void
    {
        $c = new FormComponent();

        try {
            $c->callAction('goAway');
            $this->fail('expected redirect');
        } catch (RedirectException $e) {
            $this->assertSame('/done', $e->redirect()['u']);
            $this->assertTrue($e->redirect()['spa']);
        }
    }

    public function test_dispatch_variants_and_clear(): void
    {
        $c = new KitchenSinkComponent();
        $c->name = 'Sam';
        $c->callAction('save');

        $ev = $c->getDispatchedEvents();
        $this->assertCount(3, $ev);
        $this->assertSame('saved', $ev[0]['name']);
        $this->assertSame(['Other'], $ev[1]['to']);
        $this->assertTrue($ev[2]['self']);
        $this->assertSame('toast', $c->getBrowserEvents()[0]['name']);

        $c->clearDispatchedEvents();
        $this->assertSame([], $c->getDispatchedEvents());
        $this->assertSame([], $c->getBrowserEvents());
    }

    public function test_handle_event_via_on_attribute(): void
    {
        $c = new KitchenSinkComponent();
        $c->callAction('shout', ['hello']);
        $this->assertSame('hello', $c->heard);
        $this->assertArrayHasKey('announce', $c->getEventListeners());
    }

    public function test_while_rendering_sets_current(): void
    {
        $c = new CounterComponent();
        $this->assertNull(CounterComponent::current());

        $seen = CounterComponent::whileRendering($c, function () {
            return CounterComponent::current();
        });

        $this->assertSame($c, $seen);
        $this->assertNull(CounterComponent::current());
    }

    public function test_set_id_and_url_properties(): void
    {
        $c = new KitchenSinkComponent();
        $c->setId('fixed-id');
        $this->assertSame('fixed-id', $c->getId());
        $this->assertArrayHasKey('filter', $c->getUrlProperties());
        $this->assertSame('tab', $c->getUrlProperties()['tab']['as']);
        $this->assertTrue($c->getUrlProperties()['tab']['keep']);
    }

    public function test_validate_uses_framework_rules(): void
    {
        $c = new class () extends \Doppar\Ripple\Component {
            public string $email = '';

            public string $role = '';

            protected function rules(): array
            {
                return [
                    'email' => 'required|email',
                    'role' => 'required|in:admin,user',
                ];
            }

            #[\Doppar\Ripple\Attributes\Invokable]
            public function save(): void
            {
                $this->validate();
            }

            public function render(): string
            {
                return '<div></div>';
            }
        };
        $c->email = 'ok@example.com';
        $c->role = 'nope';

        try {
            $c->callAction('save');
            $this->fail('expected in: validation');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('role', $e->errors());
        }

        $c->role = 'admin';
        $c->callAction('save');
        $this->assertSame([], $c->getErrorBag());
    }

    public function test_pagination_actions(): void
    {
        $c = new PaginatedComponent();
        $c->nextPage();
        $c->nextPage();
        $this->assertSame(3, $c->page);
        $c->previousPage();
        $this->assertSame(2, $c->page);
        $c->gotoPage(0);
        $this->assertSame(1, $c->page);
        $c->previousPage();
        $this->assertSame(1, $c->page);
    }
}
