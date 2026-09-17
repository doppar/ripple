<?php

namespace Doppar\Ripple\Tests\Fixtures;

use Doppar\Ripple\Attributes\Computed;
use Doppar\Ripple\Attributes\Invokable;
use Doppar\Ripple\Attributes\Locked;
use Doppar\Ripple\Attributes\On;
use Doppar\Ripple\Attributes\QueryString;
use Doppar\Ripple\Component;

final class KitchenSinkComponent extends Component
{
    public string $name = '';

    public int $qty = 0;

    public float $price = 0.0;

    public bool $active = false;

    /** @var list<int> */
    public array $tags = [];

    #[Locked]
    public string $token = 'locked-token';

    #[Computed]
    public string $summary = '';

    #[QueryString]
    public string $filter = 'all';

    #[QueryString(as: 'tab', keep: true)]
    public string $tab = 'home';

    public string $heard = '';

    public int $mountCalls = 0;

    public function mount(): void
    {
        $this->mountCalls++;
        $this->summary = 'n=' . $this->name;
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|min:2',
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Name please.',
            'name.min' => 'Name too short.',
        ];
    }

    #[Invokable]
    public function save(): void
    {
        $this->validate();
        $this->summary = 'saved:' . $this->name;
        $this->dispatch('saved', $this->name);
        $this->dispatchTo('Other', 'ping', 1);
        $this->dispatchSelf('local');
        $this->dispatchBrowserEvent('toast', ['ok' => true]);
    }

    #[Invokable]
    public function leave(): void
    {
        $this->redirect('/elsewhere', false);
    }

    #[On('announce')]
    public function onAnnounce(string $msg = ''): void
    {
        $this->heard = $msg;
    }

    #[Invokable]
    public function shout(string $msg): void
    {
        $this->handleEvent('announce', [$msg]);
    }

    public function render(): string
    {
        return '<div data-name="' . htmlspecialchars($this->name) . '">qty=' . $this->qty . '</div>';
    }
}
