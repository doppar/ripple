<?php

namespace Doppar\Ripple\Tests\Fixtures;

use Doppar\Ripple\Attributes\Invokable;
use Doppar\Ripple\Attributes\Locked;
use Doppar\Ripple\Component;

final class CounterComponent extends Component
{
    public int $count = 0;

    #[Locked]
    public string $secret = 'nope';

    #[Invokable]
    public function increment(): void
    {
        $this->count++;
    }

    #[Invokable]
    public function refresh(): void
    {
        //
    }

    public function render(): string
    {
        return '<div><p>Count: ' . $this->count . '</p><button type="button" ripple:call="increment">+</button></div>';
    }
}
