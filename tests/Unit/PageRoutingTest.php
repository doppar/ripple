<?php

namespace Doppar\Ripple\Tests\Unit;

use Doppar\Ripple\ComponentManager;
use Doppar\Ripple\Routing\RippleRouter;
use Doppar\Ripple\Tests\Fixtures\CounterComponent;
use Doppar\Ripple\Tests\Fixtures\PageComponent;
use Doppar\Ripple\Tests\TestCase;
use ReflectionMethod;

final class PageRoutingTest extends TestCase
{
    public function test_resolve_layout_from_attribute(): void
    {
        $manager = new ComponentManager();
        $method = new ReflectionMethod(ComponentManager::class, 'resolveLayout');

        $this->assertSame('ripple::layouts.app', $method->invoke($manager, PageComponent::class));
    }

    public function test_resolve_layout_falls_back_to_config_default(): void
    {
        $manager = new ComponentManager();
        $method = new ReflectionMethod(ComponentManager::class, 'resolveLayout');

        $this->assertSame('ripple::layouts.app', $method->invoke($manager, CounterComponent::class));
    }

    public function test_resolve_title_from_attribute(): void
    {
        $manager = new ComponentManager();
        $method = new ReflectionMethod(ComponentManager::class, 'resolveTitle');

        $this->assertSame('Page Fixture', $method->invoke($manager, PageComponent::class));
    }

    public function test_resolve_title_falls_back_to_short_class_name(): void
    {
        $manager = new ComponentManager();
        $method = new ReflectionMethod(ComponentManager::class, 'resolveTitle');

        $this->assertSame('Counter', $method->invoke($manager, CounterComponent::class));
    }

    public function test_ripple_router_registers_get_route(): void
    {
        $gateway = new class () implements \Phaseolies\Http\Contracts\GatewayInterface {
            public function getGlobalMiddleware(): array
            {
                return [];
            }

            public function getMiddlewareGroups(): array
            {
                return [];
            }

            public function getRouteMiddleware(): array
            {
                return [];
            }

            public function applyMiddleware(
                \Phaseolies\Middleware\Contracts\Middleware $middleware,
                array|string $params = [],
            ): void {
            }

            public function handle(
                \Phaseolies\Http\Request $request,
                \Closure $handler,
            ): \Phaseolies\Http\Response {
                $result = $handler($request);

                return $result instanceof \Phaseolies\Http\Response
                    ? $result
                    : new \Phaseolies\Http\Response((string) $result);
            }
        };

        $router = new RippleRouter($gateway);
        $result = $router->ripple('/ripple-page-test', PageComponent::class);

        $this->assertSame($router, $result);
        $this->assertInstanceOf(RippleRouter::class, $router);
    }

    public function test_mount_includes_data_ripple_for_page_component(): void
    {
        $html = (new ComponentManager())->mount(PageComponent::class);

        $this->assertStringContainsString('data-ripple', $html);
        $this->assertStringContainsString('data-page-body', $html);
        $this->assertStringContainsString('hello', $html);
    }
}
