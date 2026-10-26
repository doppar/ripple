<?php

namespace Doppar\Ripple\VDom;

final class Node
{
    /**
     * Create a new virtual DOM node.
     *
     * @param string $type
     * @param array<string, string|bool|null> $attrs
     * @param list<self> $children
     * @param ?string $text
     * @param ?string $key
     */
    public function __construct(
        public readonly string $type,
        public array $attrs = [],
        public array $children = [],
        public ?string $text = null,
        public ?string $key = null,
    ) {
    }

    /**
     * Determine if the node is a text node.
     *
     * @return bool
     */
    public function isText(): bool
    {
        return $this->type === '#text';
    }

    /**
     * Determine if the node is an element node.
     *
     * @return bool
     */
    public function isElement(): bool
    {
        return ! $this->isText();
    }
}
