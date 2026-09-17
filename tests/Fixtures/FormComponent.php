<?php

namespace Doppar\Ripple\Tests\Fixtures;

use Doppar\Ripple\Attributes\Invokable;
use Doppar\Ripple\Attributes\On;
use Doppar\Ripple\Component;

final class FormComponent extends Component
{
    public string $email = '';

    public string $note = '';

    protected function rules(): array
    {
        return [
            'email' => 'required|email',
        ];
    }

    #[Invokable]
    public function save(): void
    {
        $this->validate();
        $this->note = 'ok';
        $this->dispatch('saved', $this->email);
        $this->dispatchBrowserEvent('form-saved', ['email' => $this->email]);
    }

    #[Invokable]
    public function goAway(): void
    {
        $this->redirect('/done');
    }

    #[On('saved')]
    #[Invokable]
    public function onSaved(string $email = ''): void
    {
        $this->note = 'heard:' . $email;
    }

    public function render(): string
    {
        return '<div><span>' . htmlspecialchars($this->note) . '</span><span>' . htmlspecialchars($this->email) . '</span></div>';
    }
}
