<?php

namespace Doppar\Ripple\Exceptions;

final class MultipleRootsException extends \RuntimeException
{
    /**
     * Create a new exception for a template with more than one root element.
     *
     * @param list<string> $tags
     */
    public function __construct(array $tags)
    {
        $list = implode(', ', array_map(static fn (string $t) => '<' . $t . '>', $tags));

        parent::__construct(
            'Ripple components must have a single root element; found ' . count($tags) . " roots: {$list}. Wrap the template in one parent element.",
        );
    }
}
