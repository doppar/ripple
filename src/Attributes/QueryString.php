<?php

namespace Doppar\Ripple\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class QueryString
{
    /**
     * Create a new query string binding attribute instance.
     *
     * @param string|null $as
     * @param bool $history
     * @param bool $keep
     */
    public function __construct(
        public readonly ?string $as = null,
        public readonly bool $history = true,
        public readonly bool $keep = false,
    ) {
    }
}
