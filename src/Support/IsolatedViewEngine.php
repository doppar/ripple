<?php

namespace Doppar\Ripple\Support;

use Phaseolies\Http\Controllers\Controller;

/**
 * Odo's View::fetch($name, $data) uses extract($data, EXTR_SKIP), so a component
 * public $name is shadowed by the view-name parameter. This engine extracts with
 * OVERWRITE after stashing the view name under a non-conflicting local.
 */
final class IsolatedViewEngine extends Controller
{
    /**
     * Render a view, extracting data with overwrite so component props are not shadowed.
     *
     * @param string $name
     * @param array<string, mixed> $data
     * @return string
     */
    public function fetch($name, array $data = []): string
    {
        if (! is_string($name) || $name === '') {
            throw new \InvalidArgumentException('View name must be a non-empty string');
        }

        $rippleView = $name;
        $cacheKey = $this->generateCacheKey($rippleView, $data);

        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $this->renderStack[] = $rippleView;

        try {
            $this->parents[] = $rippleView;
            extract($data, EXTR_OVERWRITE);

            while ($template = array_pop($this->parents)) {
                $this->beginBlock('__current_template__');
                require $this->prepare($template);
                $this->endBlock(true);
            }

            $result = $this->block('__current_template__', '');

            return self::$cache[$cacheKey] = $result;
        } finally {
            if (! empty($this->renderStack)) {
                array_pop($this->renderStack);
            }
        }
    }
}
