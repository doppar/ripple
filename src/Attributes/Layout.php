<?php

namespace Doppar\Ripple\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Layout
{
    /**
     * Create a new layout attribute instance.
     *
     * @param string $name
     */
    public function __construct(public readonly string $name)
    {
    }
}
