<?php

namespace Doppar\Ripple\Tests\Fixtures;

use Doppar\Ripple\Attributes\Invokable;
use Doppar\Ripple\Component;

final class ArgComponent extends Component
{
    public int $n = 0;

    public string $label = '';

    #[Invokable]
    public function bump(int $by = 1): void
    {
        $this->n += $by;
    }

    #[Invokable]
    public function rename(string $label): void
    {
        $this->label = $label;
    }

    private function secretBump(): void
    {
        $this->n += 100;
    }

    public function render(): string
    {
        return '<div>' . $this->n . ':' . htmlspecialchars($this->label) . '</div>';
    }
}
