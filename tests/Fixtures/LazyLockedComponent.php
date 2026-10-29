<?php

namespace Doppar\Ripple\Tests\Fixtures;

use Doppar\Ripple\Attributes\Lazy;
use Doppar\Ripple\Attributes\Locked;
use Doppar\Ripple\Component;

#[Lazy]
final class LazyLockedComponent extends Component
{
    #[Locked]
    public int $ownerId = 1;

    public function render(): string
    {
        return '<div class="owner">' . $this->ownerId . '</div>';
    }
}
