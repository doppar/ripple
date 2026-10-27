<?php

namespace Doppar\Ripple\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Lazy
{
    /**
     * Create a new lazy-loading attribute instance.
     *
     * @param string|null $placeholder
     */
    public function __construct(public readonly ?string $placeholder = null)
    {
    }
}
