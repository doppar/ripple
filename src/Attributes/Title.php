<?php

namespace Doppar\Ripple\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Title
{
    public function __construct(public readonly string $value)
    {
    }
}
