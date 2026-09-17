<?php

namespace Doppar\Ripple\Support;

use Phaseolies\Support\View\View;
use ReflectionProperty;

/** Clears Doppar's in-memory Odo fetch cache. */
final class ViewCache
{
    public static function flush(): void
    {
        $cache = new ReflectionProperty(View::class, 'cache');
        $cache->setValue(null, []);
    }
}
