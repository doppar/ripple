<?php

namespace Doppar\Ripple\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Title
{
    /**
     * Create a new page title attribute instance.
     *
     * @param string $value
     */
    public function __construct(public readonly string $value)
    {
    }
}
