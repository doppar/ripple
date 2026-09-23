<?php

use Doppar\Ripple\Facade\Ripple;

if (! function_exists('ripple')) {
    /**
     * Mount a component from the app Ripple folder (or by class name).
     *
     * ripple('counter') resolves App\Ripple\Counter via component_namespace.
     *
     * @param array<string, mixed> $params
     */
    function ripple(string $component, array $params = []): string
    {
        return Ripple::mount($component, $params);
    }
}
