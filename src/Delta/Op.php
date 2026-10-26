<?php

namespace Doppar\Ripple\Delta;

final class Op
{
    /**
     * Op code that replaces a node with new HTML.
     *
     * Wire op codes (short; repeats on every patch).
     *
     * @var string
     */
    public const REPLACE = 'r';

    /**
     * Op code that updates a node's attributes.
     *
     * @var string
     */
    public const UPDATE_ATTRS = 'u';

    /**
     * Op code that sets a node's text content.
     *
     * @var string
     */
    public const SET_TEXT = 't';

    /**
     * Op code that inserts new HTML at a child index.
     *
     * @var string
     */
    public const INSERT = 'i';

    /**
     * Op code that removes a node.
     *
     * @var string
     */
    public const REMOVE = 'x';

    /**
     * Op code that reorders a node's children.
     *
     * @var string
     */
    public const REORDER = 'm';

    /**
     * Map of op data keys to their short wire names.
     *
     * @var array<string, string>
     */
    private const DATA_KEYS = [
        'value' => 'v',
        'html' => 'h',
        'attrs' => 'a',
        'index' => 'x',
    ];

    /**
     * Create a new patch operation.
     *
     * @param string $op
     * @param string $path
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly string $op,
        public readonly string $path,
        public readonly array $data = [],
    ) {
    }

    /**
     * Convert the operation to its compact wire form.
     *
     * Compact wire form: o=op, p=path, plus short data keys.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = ['o' => $this->op, 'p' => $this->path];

        foreach ($this->data as $key => $value) {
            $out[self::DATA_KEYS[$key] ?? $key] = $value;
        }

        return $out;
    }
}
