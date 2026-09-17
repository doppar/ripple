<?php

namespace Doppar\Ripple\Tests\Fixtures;

use Doppar\Ripple\Attributes\Lazy;
use Doppar\Ripple\Component;

#[Lazy(placeholder: '<p class="wait">Loading desk…</p>')]
final class LazyDeskComponent extends Component
{
    public string $title = 'Desk';

    public function mount(): void
    {
        $this->title = 'Loaded desk';
    }

    public function render(): string
    {
        return '<div class="desk">' . htmlspecialchars($this->title) . '</div>';
    }
}
