<?php

namespace Doppar\Ripple\Traits;

use Doppar\Ripple\Attributes\Invokable;

trait HasPagination
{
    public int $page = 1;

    public int $perPage = 15;

    #[Invokable]
    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    #[Invokable]
    public function nextPage(): void
    {
        $this->page++;
    }

    #[Invokable]
    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }
}
