<?php

namespace Doppar\Ripple\Support;

use Phaseolies\Support\View\View;
use ReflectionProperty;

/** Clears Doppar's in-memory Odo fetch cache. */
final class ViewCache
{
    /**
     * Reset the static Odo view fetch cache.
     *
     * @return void
     */
    public static function flush(): void
    {
        $cache = new ReflectionProperty(View::class, 'cache');
        $cache->setValue(null, []);
    }
}
