<?php

namespace Doppar\Ripple\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class QueryString
{
    public function __construct(
        public readonly ?string $as = null,
        public readonly bool $history = true,
        public readonly bool $keep = false,
    ) {
    }
}
