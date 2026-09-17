<?php

namespace Doppar\Ripple\Exceptions;

final class RedirectException extends \RuntimeException
{
    /**
     * @param array{u: string, spa?: bool} $redirect
     */
    public function __construct(private readonly array $redirect)
    {
        parent::__construct('Ripple redirect');
    }

    /**
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
