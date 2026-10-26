<?php

namespace Doppar\Ripple\Routing;

use Doppar\Ripple\ComponentManager;
use Phaseolies\Http\Contracts\GatewayInterface;
use Phaseolies\Http\Request;
use Phaseolies\Support\Router;

final class RippleRouter extends Router
{
    /**
     * Create a new Ripple router instance.
     *
     * @param GatewayInterface $gateway
     */
    public function __construct(GatewayInterface $gateway)
    {
        parent::__construct($gateway);
    }

    /**
     * Register a full-page Ripple component route.
     *
     * @param string $uri
     * @param class-string<\Doppar\Ripple\Component>|string $component
     * @return self
     */
    public function ripple(string $uri, string $component): self
    {
        $this->get($uri, function (Request $request) use ($component) {
            /** @var ComponentManager $manager */
            $manager = app(ComponentManager::class);

            return $manager->renderPage($component, $request->getRouteParams());
        });

        return $this;
    }
}
