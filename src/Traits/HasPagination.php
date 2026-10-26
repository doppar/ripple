<?php

namespace Doppar\Ripple\Traits;

use Doppar\Ripple\Attributes\Invokable;
use Doppar\Ripple\Attributes\QueryString;

trait HasPagination
{
    /**
     * The current page number, synced to the query string.
     *
     * @var int
     */
    #[QueryString]
    public int $page = 1;

    /**
     * The number of items to show per page.
     *
     * @var int
     */
    public int $perPage = 15;

    /**
     * Jump to the given page, clamped to a minimum of 1.
     *
     * @param int $page
     * @return void
     */
    #[Invokable]
    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    /**
     * Advance to the next page.
     *
     * @return void
     */
    #[Invokable]
    public function nextPage(): void
    {
        $this->page++;
    }

    /**
     * Go back to the previous page, never going below page 1.
     *
     * @return void
     */
    #[Invokable]
    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }
}
