<?php

namespace Doppar\Ripple\Facade;

use Doppar\Ripple\ComponentManager;
use Phaseolies\Facade\BaseFacade;

/**
 * @method static string mount(string $component, array $params = [])
 * @method static \Phaseolies\Http\Response renderPage(string $component, array $params = [])
 * @method static string scripts()
 * @method static void register(string $alias, string $class)
 * @method static array update(array $payload)
 * @method static array loadLazy(string $component, array $params = [], ?string $id = null)
 * @method static \Doppar\Ripple\Testing\Testable test(string $component, array $params = [])
 *
 * @see \Doppar\Ripple\ComponentManager
 */
class Ripple extends BaseFacade
{
    /**
     * Get the service container binding the facade resolves to.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return ComponentManager::class;
    }
}
