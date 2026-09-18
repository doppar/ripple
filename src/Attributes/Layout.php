<?php

namespace Doppar\Ripple\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Layout
{
    public function __construct(public readonly string $name)
    {
    }
}
