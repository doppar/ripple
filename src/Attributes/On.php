<?php

namespace Doppar\Ripple\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class On
{
    /**
     * Create a new event listener attribute instance.
     *
     * @param string $event
     */
    public function __construct(public readonly string $event)
    {
    }
}
