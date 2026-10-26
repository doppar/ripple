<?php

namespace Doppar\Ripple\Support;

use Doppar\Ripple\Component;
use Phaseolies\Http\Controllers\Controller;

/**
 * Merges Ripple public props into view data, and renders component views on an
 * isolated engine so nested view() calls do not corrupt an in-flight Odo #extends page.
 */
final class RippleViewController extends Controller
{
    /**
     * Render a view, merging the current component's public props and isolating component renders.
     *
     * @param string $name
     * @param array<string, mixed> $data
     * @param bool $returnOnly
     * @return string
     */
    public function render($name, array $data = [], $returnOnly = false)
    {
        if ($component = Component::current()) {
            $data = array_merge($component->viewData(), $data);
            $isolated = new IsolatedViewEngine();

            return $isolated->render($name, $data, $returnOnly);
        }

        return parent::render($name, $data, $returnOnly);
    }
}
