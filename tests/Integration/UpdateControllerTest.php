<?php

namespace Doppar\Ripple\Tests\Integration;

use Doppar\Ripple\ComponentManager;
use Doppar\Ripple\Http\UpdateController;
use Doppar\Ripple\State\StateSigner;
use Doppar\Ripple\Tests\Fixtures\CounterComponent;
use Doppar\Ripple\Tests\Fixtures\FormComponent;
use Doppar\Ripple\Tests\Fixtures\LazyDeskComponent;
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

    public function test_handle_returns_422_on_validation(): void
    {
        $response = $this->controller()->handle($this->requestWith($this->withSignature([
            'i' => 'f1',
            'n' => FormComponent::class,
            't' => 'a',
            'a' => 'save',
            'g' => [],
            's' => ['email' => '', 'note' => ''],
        ], $this->signer)));

        $this->assertSame(422, $response->getStatusCode());
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
        ]));
        $payload = $response->getData();

        $this->assertTrue($payload['ok'] ?? false);
        $this->assertSame('lz-1', $payload['i'] ?? null);
        $this->assertStringContainsString('Loaded desk', $payload['h'] ?? '');
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
