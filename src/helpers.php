<?php

use Doppar\Ripple\Facade\Ripple;

if (! function_exists('ripple')) {
    /**
     * Mount a component from the app Ripple folder (or by class name).
     *
     * ripple('counter') resolves App\Ripple\Counter via component_namespace.
     *
     * @param string $component
     * @param array<string, mixed> $params
     * @return string
     */
    function ripple(string $component, array $params = []): string
    {
        return Ripple::mount($component, $params);
    }
}

if (! function_exists('ripple_scripts')) {
    /**
     * Render the Ripple client script and its configuration meta tags.
     *
     * ripple_scripts() resolves \Doppar\Ripple\Facade\Ripple::scripts().
     *
     * @return string
     */
    function ripple_scripts(): string
    {
        return Ripple::scripts();
    }
}
