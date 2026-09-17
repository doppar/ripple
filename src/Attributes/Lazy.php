<?php

namespace Doppar\Ripple\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Lazy
{
    public function __construct(public readonly ?string $placeholder = null) {}
}
