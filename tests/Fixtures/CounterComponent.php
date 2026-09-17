<?php

namespace Doppar\Ripple\Tests\Fixtures;

use Doppar\Ripple\Component;

final class CounterComponent extends Component
{
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }

    public function render(): string
    {
        return '<div><p>Count: ' . $this->count . '</p><button type="button" ripple-call="increment">+</button></div>';
    }
}
