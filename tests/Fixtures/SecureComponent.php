<?php

namespace Doppar\Ripple\Tests\Fixtures;

use Doppar\Ripple\Attributes\Invokable;
use Doppar\Ripple\Attributes\Locked;
use Doppar\Ripple\Component;

final class SecureComponent extends Component
{
    #[Locked]
    public int $userId = 1;

    #[Locked]
    public bool $isAdmin = false;

    public string $note = '';

    protected string $apiToken = 'secret-token';

    /**
     * Public but not #[Invokable]: the browser must never reach it.
     */
    public function promote(): void
    {
        $this->isAdmin = true;
    }

    #[Invokable]
    public function save(): void
    {
        //
    }

    public function render(): string
    {
        return '<div>' . $this->userId . ' ' . ($this->isAdmin ? 'admin' : 'user') . '</div>';
    }
}
