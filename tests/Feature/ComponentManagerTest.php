<?php

namespace Doppar\Ripple\Tests\Feature;

use Doppar\Ripple\ComponentManager;
use Doppar\Ripple\Delta\Op;
use Doppar\Ripple\State\StateSigner;
use Doppar\Ripple\Tests\Fixtures\ArgComponent;
use Doppar\Ripple\Tests\Fixtures\CounterComponent;
use Doppar\Ripple\Tests\Fixtures\FormComponent;
use Doppar\Ripple\Tests\Fixtures\KitchenSinkComponent;
use Doppar\Ripple\Tests\Fixtures\LazyDeskComponent;
use Doppar\Ripple\Tests\Support\AppHarness;
use Doppar\Ripple\Tests\TestCase;

final class ComponentManagerTest extends TestCase
{
    private ComponentManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new ComponentManager(signer: new StateSigner('test-manager-key'));
    }

    public function test_mount_wraps_component_with_ripple_root(): void
    {
        $html = $this->manager->mount(CounterComponent::class);

        $this->assertStringContainsString('data-ripple', $html);
        $this->assertStringContainsString('data-ripple-id=', $html);
        $this->assertStringContainsString('data-ripple-name="' . CounterComponent::class . '"', $html);
        $this->assertStringContainsString('data-ripple-x=', $html);
        $this->assertStringContainsString('data-ripple-el=', $html);
        $this->assertStringContainsString('Count: 0', $html);
        $this->assertStringContainsString('ripple:call="increment"', $html);
    }

    public function test_mount_lazy_returns_placeholder(): void
    {
        $html = $this->manager->mount(LazyDeskComponent::class, ['title' => 'x']);

        $this->assertStringContainsString('data-ripple-lazy', $html);
        $this->assertStringContainsString('Loading desk…', $html);
        $this->assertStringContainsString('data-ripple-params=', $html);
        $this->assertStringNotContainsString('Loaded desk', $html);
    }

    public function test_load_lazy_hydrates_full_html(): void
    {
        $result = $this->manager->loadLazy(LazyDeskComponent::class, [], 'lazy-1');

        $this->assertTrue($result['ok']);
        $this->assertSame('lazy-1', $result['i']);
        $this->assertStringContainsString('Loaded desk', $result['h']);
        $this->assertArrayHasKey('x', $result);
        $this->assertArrayHasKey('f', $result);
    }

    public function test_make_returns_instance(): void
    {
        $c = $this->manager->make(CounterComponent::class, ['count' => 3]);
        $this->assertInstanceOf(CounterComponent::class, $c);
        $this->assertSame(3, $c->count);
    }

    public function test_scripts_emit_metas(): void
    {
        $harness = new AppHarness(sys_get_temp_dir() . '/ripple-scripts-' . bin2hex(random_bytes(4)));

        try {
            $harness->instance('session', new class () {
                public function get(mixed $key, mixed $default = null): mixed
                {
                    return $key === '_token' ? 'test-csrf' : $default;
                }
            });

            $html = $this->manager->scripts();
            $this->assertStringContainsString('name="csrf-token"', $html);
            $this->assertStringContainsString('test-csrf', $html);
            $this->assertStringContainsString('name="ripple-update"', $html);
            $this->assertStringContainsString('name="ripple-lazy"', $html);
            $this->assertStringContainsString('name="ripple-upload"', $html);
            $this->assertStringContainsString('ripple.min.js', $html);
        } finally {
            $harness->destroy();
        }
    }

    public function test_update_returns_short_wire_payload_with_delta(): void
    {
        $result = $this->manager->update($this->withSignature([
            'i' => 'abc',
            'n' => CounterComponent::class,
            't' => 'a',
            'a' => 'increment',
            'g' => [],
            's' => ['count' => 0, 'secret' => 'nope'],
        ], $this->manager->signer()));

        $this->assertSame('abc', $result['i']);
        $this->assertSame(CounterComponent::class, $result['n']);
        $this->assertSame(1, $result['s']['count']);
        $this->assertArrayHasKey('x', $result);
        $this->assertNotEmpty($result['d']);

        $setText = array_values(array_filter(
            $result['d'],
            static fn (array $o) => ($o['o'] ?? '') === Op::SET_TEXT,
        ));
        $this->assertNotEmpty($setText);
        $this->assertSame('Count: 1', $setText[0]['v'] ?? null);
    }

    public function test_update_action_with_args(): void
    {
        $result = $this->manager->update($this->withSignature([
            'i' => 'a1',
            'n' => ArgComponent::class,
            't' => 'a',
            'a' => 'bump',
            'g' => [3],
            's' => ['n' => 1, 'label' => ''],
        ], $this->manager->signer()));

        $this->assertSame(4, $result['s']['n']);
    }

    public function test_update_property_path(): void
    {
        $state = ['count' => 0, 'secret' => 'nope'];
        $id = 'abc';
        $x = $this->manager->signer()->sign($state, $id);

        $result = $this->manager->update([
            'i' => $id,
            'n' => CounterComponent::class,
            't' => 'u',
            'p' => 'count',
            'v' => 9,
            's' => $state,
            'x' => $x,
        ]);

        $this->assertSame(9, $result['s']['count']);
    }

    public function test_property_update_requires_valid_signature(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Invalid Ripple state signature');

        $this->manager->update([
            'i' => 'abc',
            'n' => CounterComponent::class,
            't' => 'u',
            'p' => 'count',
            'v' => 1,
            's' => ['count' => 0, 'secret' => 'nope'],
            'x' => 'bad-signature',
        ]);
    }

    public function test_property_update_requires_p(): void
    {
        $state = ['count' => 0, 'secret' => 'nope'];
        $id = 'abc';
        $x = $this->manager->signer()->sign($state, $id);

        $this->expectException(\InvalidArgumentException::class);
        $this->manager->update([
            'i' => $id,
            'n' => CounterComponent::class,
            't' => 'u',
            'p' => '',
            'v' => 1,
            's' => $state,
            'x' => $x,
        ]);
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

    public function test_unknown_component_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->manager->mount('TotallyMissingComponent');
    }

    public function test_validation_errors_soft_fail_with_e(): void
    {
        $result = $this->manager->update($this->withSignature([
            'i' => 'f1',
            'n' => FormComponent::class,
            't' => 'a',
            'a' => 'save',
            'g' => [],
            's' => ['email' => '', 'note' => ''],
        ], $this->manager->signer()));

        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey('e', $result);
        $this->assertArrayHasKey('email', $result['e']);
    }

    public function test_redirect_payload(): void
    {
        $result = $this->manager->update($this->withSignature([
            'i' => 'f1',
            'n' => FormComponent::class,
            't' => 'a',
            'a' => 'goAway',
            'g' => [],
            's' => ['email' => 'a@b.co', 'note' => ''],
        ], $this->manager->signer()));

        $this->assertTrue($result['ok']);
        $this->assertSame('/done', $result['rd']['u'] ?? null);
        $this->assertTrue($result['rd']['spa'] ?? false);
    }

    public function test_redirect_payload_hard_disables_spa(): void
    {
        $state = (new KitchenSinkComponent())->getState();
        $result = $this->manager->update($this->withSignature([
            'i' => 'k1',
            'n' => KitchenSinkComponent::class,
            't' => 'a',
            'a' => 'leave',
            'g' => [],
            's' => $state,
        ], $this->manager->signer()));

        $this->assertTrue($result['ok']);
        $this->assertSame('/elsewhere', $result['rd']['u'] ?? null);
        $this->assertFalse($result['rd']['spa'] ?? true);
    }

    public function test_dispatch_in_response(): void
    {
        $result = $this->manager->update($this->withSignature([
            'i' => 'f1',
            'n' => FormComponent::class,
            't' => 'a',
            'a' => 'save',
            'g' => [],
            's' => ['email' => 'a@b.co', 'note' => ''],
        ], $this->manager->signer()));

        $this->assertTrue($result['ok']);
        $this->assertNotEmpty($result['ev'] ?? []);
        $this->assertSame('saved', $result['ev'][0]['name']);
        $this->assertNotEmpty($result['be'] ?? []);
        $this->assertSame('form-saved', $result['be'][0]['name']);
    }

    public function test_query_string_included_when_url_props_exist(): void
    {
        $state = (new KitchenSinkComponent())->getState();
        $signedState = array_merge($state, ['filter' => 'open', 'name' => 'x']);
        $result = $this->manager->update($this->withSignature([
            'i' => 'k1',
            'n' => KitchenSinkComponent::class,
            't' => 'a',
            'a' => 'shout',
            'g' => ['hi'],
            's' => $signedState,
        ], $this->manager->signer()));

        $this->assertArrayHasKey('qs', $result);
        $this->assertSame('open', $result['qs']['filter']);
        $this->assertArrayHasKey('tab', $result['qs']);
    }

    public function test_error_bag_round_trips_on_payload(): void
    {
        $state = ['email' => 'bad', 'note' => ''];
        $result = $this->manager->update([
            'i' => 'f2',
            'n' => FormComponent::class,
            't' => 'u',
            'p' => 'email',
            'v' => 'still-bad',
            's' => $state,
            'x' => $this->manager->signer()->sign($state, 'f2'),
            'er' => ['note' => ['kept']],
        ]);

        $this->assertArrayHasKey('e', $result);
        $this->assertSame(['kept'], $result['e']['note'] ?? null);
        $this->assertArrayHasKey('email', $result['e']);
    }
}
