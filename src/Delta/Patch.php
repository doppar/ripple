<?php

namespace Doppar\Ripple\Delta;

final class Patch
{
    /** @var list<Op> */
    private array $ops = [];

    public function add(Op $op): void
    {
        $this->ops[] = $op;
    }

    /**
     * @return list<Op>
     */
    public function ops(): array
    {
        return $this->ops;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(static fn (Op $op) => $op->toArray(), $this->ops);
    }

    public function isEmpty(): bool
    {
        return $this->ops === [];
    }
}
