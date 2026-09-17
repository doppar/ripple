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
    public function test_handle_accepts_short_keys_and_returns_deltas(): void
    {
        $controller = new UpdateController(new ComponentManager());

        $request = new Request();
        $body = json_encode([
            'i' => 'int-1',
            'n' => CounterComponent::class,
            'a' => 'increment',
            'g' => [],
            's' => ['count' => 4],
        ], JSON_THROW_ON_ERROR);

        $content = new ReflectionProperty($request, 'content');
        $content->setValue($request, $body);

        $response = $controller->handle($request);
        $payload = $response->getData();

        $this->assertIsArray($payload);
        $this->assertSame('int-1', $payload['i'] ?? null);
        $this->assertSame(5, $payload['s']['count'] ?? null);
        $this->assertArrayHasKey('d', $payload);
        $this->assertNotEmpty($payload['d']);
    }

    public function test_handle_returns_error_payload_on_unknown_component(): void
    {
        $controller = new UpdateController(new ComponentManager());

        $request = new Request();
        $body = json_encode([
            'i' => 'x',
            'n' => 'Missing\\Component',
            'a' => null,
            'g' => [],
            's' => [],
        ], JSON_THROW_ON_ERROR);

        $content = new ReflectionProperty($request, 'content');
        $content->setValue($request, $body);

        $response = $controller->handle($request);

        $this->assertSame(422, $response->getStatusCode());
        $payload = $response->getData();
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('e', $payload);
    }
}