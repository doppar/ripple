<?php

namespace Doppar\Ripple\Tests\Fixtures;

use Doppar\Ripple\Attributes\Invokable;
use Doppar\Ripple\Attributes\Layout;
use Doppar\Ripple\Attributes\Title;
use Doppar\Ripple\Component;

#[Layout('ripple::layouts.app')]
#[Title('Page Fixture')]
final class PageComponent extends Component
{
    public string $label = 'hello';

    #[Invokable]
    public function ping(): void
    {
        $this->label = 'pong';
    }

    public function render(): string
    {
        return '<div data-page-body>' . htmlspecialchars($this->label) . '</div>';
    }
}
