<?php

namespace Doppar\Ripple\Tests\Fixtures;

use Doppar\Ripple\Component;
use Doppar\Ripple\Traits\HasPagination;

final class PaginatedComponent extends Component
{
    use HasPagination;

    public function render(): string
    {
        return '<div>page ' . $this->page . '</div>';
    }
}
