<?php

namespace Doppar\Ripple\Delta;

final class Patch
{
    /**
     * The operations collected in this patch.
     *
     * @var list<Op>
     */
    private array $ops = [];

    /**
     * Append an operation to the patch.
     *
     * @param Op $op
     * @return void
     */
    public function add(Op $op): void
    {
        $this->ops[] = $op;
    }

    /**
     * Get the operations collected in this patch.
     *
     * @return list<Op>
     */
    public function ops(): array
    {
        return $this->ops;
    }

    /**
     * Convert every operation to its compact wire form.
     *
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(static fn (Op $op) => $op->toArray(), $this->ops);
    }

    /**
     * Determine if the patch contains no operations.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->ops === [];
    }
}
