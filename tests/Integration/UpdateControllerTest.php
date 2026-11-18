<?php

namespace Doppar\Ripple\Tests\Integration;

use Doppar\Ripple\ComponentManager;
use Doppar\Ripple\Http\UpdateController;
use Doppar\Ripple\State\StateSigner;
use Doppar\Ripple\Tests\Fixtures\CounterComponent;
use Doppar\Ripple\Tests\Fixtures\FormComponent;
use Doppar\Ripple\Tests\Fixtures\LazyDeskComponent;
use Doppar\Ripple\Tests\Fixtures\LazyLockedComponent;
use Doppar\Ripple\Tests\TestCase;
use Phaseolies\Http\Request;
use ReflectionProperty;

final class UpdateControllerTest extends TestCase
{
    private StateSigner $signer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signer = new StateSigner('ctrl-key');
    }

    private function requestWith(array $body): Request
    {
        $request = new Request();
        $content = new ReflectionProperty($request, 'content');
        $content->setValue($request, json_encode($body, JSON_THROW_ON_ERROR));

        return $request;
    }

    private function controller(): UpdateController
    {
        return new UpdateController(new ComponentManager(signer: $this->signer));
    }

    public function test_handle_accepts_short_keys_and_returns_deltas(): void
    {
        $response = $this->controller()->handle($this->requestWith($this->withSignature([
            'i' => 'int-1',
            'n' => CounterComponent::class,
            't' => 'a',
            'a' => 'increment',
            'g' => [],
            's' => ['count' => 4, 'secret' => 'nope'],
        ], $this->signer)));
        $payload = $response->getData();

        $this->assertIsArray($payload);
        $this->assertSame('int-1', $payload['i'] ?? null);
        $this->assertSame(5, $payload['s']['count'] ?? null);
        $this->assertArrayHasKey('d', $payload);
        $this->assertNotEmpty($payload['d']);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_handle_returns_ok_false_on_validation(): void
    {
        $response = $this->controller()->handle($this->requestWith($this->withSignature([
            'i' => 'f1',
            'n' => FormComponent::class,
            't' => 'a',
            'a' => 'save',
            'g' => [],
            's' => ['email' => '', 'note' => ''],
        ], $this->signer)));

        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData();
        $this->assertFalse($payload['ok'] ?? true);
        $this->assertArrayHasKey('email', $payload['e'] ?? []);
    }

    public function test_lazy_endpoint(): void
    {
        $response = $this->controller()->lazy($this->requestWith([
            'n' => LazyDeskComponent::class,
            'i' => 'lz-1',
            'g' => [],
            'x' => $this->signer->signLazy(LazyDeskComponent::class, [], 'lz-1'),
        ]));
        $payload = $response->getData();

        $this->assertTrue($payload['ok'] ?? false);
        $this->assertSame('lz-1', $payload['i'] ?? null);
        $this->assertStringContainsString('Loaded desk', $payload['h'] ?? '');
    }

    public function test_lazy_endpoint_accepts_placeholder_signature(): void
    {
        $manager = new ComponentManager(signer: $this->signer);
        $placeholder = $manager->mount(LazyLockedComponent::class, ['ownerId' => 7]);

        preg_match('/data-ripple-id="([^"]+)"/', $placeholder, $id);
        preg_match('/data-ripple-lx="([^"]+)"/', $placeholder, $sig);

        $payload = (new UpdateController($manager))->lazy($this->requestWith([
            'n' => LazyLockedComponent::class,
            'i' => $id[1],
            'g' => ['ownerId' => 7],
            'x' => $sig[1],
        ]))->getData();

        $this->assertSame(7, $payload['s']['ownerId'] ?? null);
    }

    public function test_lazy_endpoint_rejects_forged_locked_params(): void
    {
        $signature = $this->signer->signLazy(LazyLockedComponent::class, ['ownerId' => 7], 'lz-2');

        $response = $this->controller()->lazy($this->requestWith([
            'n' => LazyLockedComponent::class,
            'i' => 'lz-2',
            'g' => ['ownerId' => 99],
            'x' => $signature,
        ]));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertFalse($response->getData()['ok'] ?? true);
        $this->assertSame('Invalid Ripple lazy signature.', $response->getData()['message'] ?? null);
    }

    public function test_lazy_endpoint_rejects_missing_signature(): void
    {
        $response = $this->controller()->lazy($this->requestWith([
            'n' => LazyLockedComponent::class,
            'i' => 'lz-3',
            'g' => ['ownerId' => 99],
        ]));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertFalse($response->getData()['ok'] ?? true);
        $this->assertSame('Invalid Ripple lazy signature.', $response->getData()['message'] ?? null);
    }

    public function test_lazy_endpoint_rejects_non_lazy_component(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is not lazy');

        $this->controller()->lazy($this->requestWith([
            'n' => CounterComponent::class,
            'i' => 'lz-4',
            'g' => ['count' => 5],
            'x' => $this->signer->signLazy(CounterComponent::class, ['count' => 5], 'lz-4'),
        ]));
    }

    public function test_lazy_signature_does_not_validate_as_state_signature(): void
    {
        $lazy = $this->signer->signLazy(LazyLockedComponent::class, [], 'lz-5');

        $this->assertFalse($this->signer->verify([], 'lz-5', LazyLockedComponent::class, $lazy));
    }

    public function test_state_signature_with_crafted_id_does_not_validate_as_lazy_signature(): void
    {
        // A non-strict verify mode lets a client get state signed under any id it chooses.
        $state = ['ownerId' => 99];
        $forged = $this->signer->sign($state, 'lazy|a|' . LazyLockedComponent::class, LazyLockedComponent::class);

        $this->assertFalse($this->signer->verifyLazy(LazyLockedComponent::class, $state, 'a', $forged));

        $response = $this->controller()->lazy($this->requestWith([
            'n' => LazyLockedComponent::class,
            'i' => 'a',
            'g' => $state,
            'x' => $forged,
        ]));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertFalse($response->getData()['ok'] ?? true);
        $this->assertSame('Invalid Ripple lazy signature.', $response->getData()['message'] ?? null);
    }

    public function test_unknown_action_bubbles_for_doppar_error_handler(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessageIsOrContains('decrementt');

        $this->controller()->handle($this->requestWith($this->withSignature([
            'i' => 'x',
            'n' => CounterComponent::class,
            't' => 'a',
            'a' => 'decrementt',
            'g' => [],
            's' => ['count' => 0, 'secret' => 'nope'],
        ], $this->signer)));
    }

    public function test_unknown_component_bubbles_for_doppar_error_handler(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Missing\\Component');

        $this->controller()->handle($this->requestWith($this->withSignature([
            'i' => 'x',
            'n' => 'Missing\\Component',
            'a' => null,
            'g' => [],
            's' => [],
        ], $this->signer)));
    }

    public function test_empty_body_still_requires_component_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->controller()->handle(new Request());
    }
}
