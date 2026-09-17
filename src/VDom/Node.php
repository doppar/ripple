<?php

namespace Doppar\Ripple\VDom;

final class Node
{
    /**
     * @param array<string, string|bool|null> $attrs
     * @param list<self> $children
     */
    public function __construct(
        public readonly string $type,
        public array $attrs = [],
        public array $children = [],
        public ?string $text = null,
        public ?string $key = null,
    ) {}

    public function isText(): bool
    {
        return $this->type === '#text';
    }

    public function isElement(): bool
    {
        return !$this->isText();
    }
}
