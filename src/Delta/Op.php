<?php

namespace Doppar\Ripple\Delta;

final class Op
{
    /** Wire op codes (short; repeats on every patch). */
    public const REPLACE = 'r';
    public const UPDATE_ATTRS = 'u';
    public const SET_TEXT = 't';
    public const INSERT = 'i';
    public const REMOVE = 'x';
    public const REORDER = 'm';

    /** @var array<string, string> */
    private const DATA_KEYS = [
        'value' => 'v',
        'html' => 'h',
        'attrs' => 'a',
        'index' => 'x',
    ];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly string $op,
        public readonly string $path,
        public readonly array $data = [],
    ) {
    }

    /**
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
