<?php

namespace Doppar\Ripple\Tests\Feature;

use Doppar\Ripple\ComponentManager;
use Doppar\Ripple\State\StateSigner;
use Doppar\Ripple\Tests\Fixtures\CounterComponent;
use Doppar\Ripple\Tests\Fixtures\FormComponent;
use Doppar\Ripple\Tests\Fixtures\LazyDeskComponent;
use Doppar\Ripple\Tests\TestCase;

final class TestableTest extends TestCase
{
    private ComponentManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new ComponentManager(signer: new StateSigner('test-testable-key'));
    }

    public function test_call_and_assert_set_and_see(): void
    {
        $this->manager->test(CounterComponent::class)
            ->assertSet('count', 0)
            ->assertSee('Count: 0')
            ->call('increment')
            ->assertOk()
            ->assertSet('count', 1)
            ->assertSee('Count: 1')
            ->set('count', 10)
            ->assertSet('count', 10)
            ->assertSee('Count: 10');
    }

    public function test_fill_and_toggle(): void
    {
        $this->manager->test(FormComponent::class)
            ->fill(['email' => 'a@b.co', 'note' => 'x'])
            ->assertSet('email', 'a@b.co')
            ->assertSet('note', 'x');
    }

    public function test_validation_errors(): void
    {
        $this->manager->test(FormComponent::class)
            ->set('email', 'bad')
            ->call('save')
            ->assertNotOk()
            ->assertHasErrors('email')
            ->assertHasNoErrors('note');
    }

    public function test_dispatch_and_browser_event(): void
    {
        $this->manager->test(FormComponent::class)
            ->set('email', 'ok@example.com')
            ->call('save')
            ->assertOk()
            ->assertHasNoErrors()
            ->assertSet('note', 'ok')
            ->assertDispatched('saved', ['ok@example.com'])
            ->assertBrowserEvent('form-saved', ['email' => 'ok@example.com']);
    }

    public function test_redirect(): void
    {
        $this->manager->test(FormComponent::class)
            ->call('goAway')
            ->assertRedirect('/done');
    }

    public function test_assert_view_has(): void
    {
        $this->manager->test(CounterComponent::class, ['count' => 3])
            ->assertViewHas('count', 3)
            ->assertViewHas('count', fn (mixed $v): bool => $v === 3);
    }

    public function test_assert_not_dispatched(): void
    {
        $this->manager->test(FormComponent::class)
            ->call('goAway')
            ->assertNotDispatched('saved');
    }

    public function test_lazy_component_loads_full_html(): void
    {
        $this->manager->test(LazyDeskComponent::class, ['title' => 'desk'])
            ->assertOk()
            ->assertSee('Loaded desk', escape: false);
    }

    public function test_lazy_mount_placeholder_then_load_lazy(): void
    {
        $placeholder = $this->manager->mount(LazyDeskComponent::class, ['title' => 'x']);

        $this->assertStringContainsString('data-ripple-lazy', $placeholder);
        $this->assertStringContainsString('Loading desk…', $placeholder);
        $this->assertStringContainsString('data-ripple-params=', $placeholder);
        $this->assertStringNotContainsString('Loaded desk', $placeholder);

        preg_match('/data-ripple-id="([^"]+)"/', $placeholder, $m);
        $id = $m[1] ?? 'lazy-test';

        $result = $this->manager->loadLazy(LazyDeskComponent::class, ['title' => 'x'], $id);

        $this->assertTrue($result['ok']);
        $this->assertSame($id, $result['i']);
        $this->assertStringContainsString('Loaded desk', $result['h']);
        $this->assertArrayHasKey('x', $result);
        $this->assertArrayHasKey('f', $result);
        $this->assertArrayHasKey('s', $result);
    }
}
