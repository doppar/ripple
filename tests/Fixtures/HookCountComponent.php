<?php

namespace Doppar\Ripple\Tests\Fixtures;

use Doppar\Ripple\Attributes\Invokable;
use Doppar\Ripple\Component;

final class HookCountComponent extends Component
{
    public int $page = 3;

    public int $perPage = 5;

    public bool $subscribed = false;

    public string $note = '';

    public int $hooks = 0;

    public function updated(string $property): void
    {
        $this->hooks++;

        if ($property === 'perPage') {
            $this->page = 1;
        }
    }

    #[Invokable]
    public function previous(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    public function render(): string
    {
        return '<div><span>' . $this->page . '</span><span>' . $this->perPage . '</span></div>';
    }
}
