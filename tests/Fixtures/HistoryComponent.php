<?php

namespace Doppar\Ripple\Tests\Fixtures;

use Doppar\Ripple\Attributes\QueryString;
use Doppar\Ripple\Component;

final class HistoryComponent extends Component
{
    #[QueryString]
    public string $q = '';

    #[QueryString(history: false)]
    public string $sort = 'newest';

    public function render(): string
    {
        return '<div>' . htmlspecialchars($this->q . '/' . $this->sort) . '</div>';
    }
}
