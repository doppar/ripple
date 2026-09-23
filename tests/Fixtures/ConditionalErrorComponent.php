<?php

namespace Doppar\Ripple\Tests\Fixtures;

use Doppar\Ripple\Attributes\Invokable;
use Doppar\Ripple\Component;

/** Mirrors app forms that only render error nodes when the bag is non-empty. */
final class ConditionalErrorComponent extends Component
{
    public string $email = '';

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
    }

    public function render(): string
    {
        $errors = $this->getErrorBag();
        $msg = $errors['email'][0] ?? '';
        $errorHtml = $msg !== ''
            ? '<p class="text-danger">' . htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
            : '';

        return '<div><input value="' . htmlspecialchars($this->email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . $errorHtml . '</div>';
    }
}
