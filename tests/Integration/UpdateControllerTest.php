<?php

namespace Doppar\Ripple\Tests\Integration;

use Doppar\Ripple\ComponentManager;
use Doppar\Ripple\Http\UpdateController;
use Doppar\Ripple\Tests\Fixtures\CounterComponent;
use Doppar\Ripple\Tests\TestCase;
use Phaseolies\Http\Request;
use ReflectionProperty;

final class UpdateControllerTest extends TestCase
{
    private function requestWith(array $body): Request
    {
        $request = new Request();
        $content = new ReflectionProperty($request, 'content');
        $content->setValue($request, json_encode($body, JSON_THROW_ON_ERROR));

        return $request;
    }

    public function test_handle_accepts_short_keys_and_returns_deltas(): void
    {
        $controller = new UpdateController(new ComponentManager());
        $response = $controller->handle($this->requestWith([
            'i' => 'int-1',
            'n' => CounterComponent::class,
            't' => 'a',
            'a' => 'increment',
            'g' => [],
            's' => ['count' => 4, 'secret' => 'nope'],
        ]));
        $payload = $response->getData();

        $this->assertIsArray($payload);
        $this->assertSame('int-1', $payload['i'] ?? null);
        $this->assertSame(5, $payload['s']['count'] ?? null);
        $this->assertArrayHasKey('d', $payload);
        $this->assertNotEmpty($payload['d']);
    }

    public function test_unknown_action_bubbles_for_doppar_error_handler(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('decrementt');

        $controller = new UpdateController(new ComponentManager());
        $controller->handle($this->requestWith([
            'i' => 'x',
            'n' => CounterComponent::class,
            't' => 'a',
            'a' => 'decrementt',
            'g' => [],
            's' => ['count' => 0, 'secret' => 'nope'],
        ]));
    }

    public function test_unknown_component_bubbles_for_doppar_error_handler(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing\\Component');

        $controller = new UpdateController(new ComponentManager());
        $controller->handle($this->requestWith([
            'i' => 'x',
            'n' => 'Missing\\Component',
            'a' => null,
            'g' => [],
            's' => [],
        ]));
    }
}
