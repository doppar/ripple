<?php

namespace Doppar\Ripple\Exceptions;

final class RedirectException extends \RuntimeException
{
    /**
     * Create a new redirect exception carrying the target URL.
     *
     * @param array{u: string, spa?: bool} $redirect
     */
    public function __construct(private readonly array $redirect)
    {
        parent::__construct('Ripple redirect');
    }

    /**
     * Get the redirect target with the soft-navigation flag defaulted to true.
     *
     * @return array{u: string, spa: bool}
     */
    public function redirect(): array
    {
        return [
            'u' => $this->redirect['u'],
            'spa' => (bool) ($this->redirect['spa'] ?? true),
        ];
    }
}
