<?php

namespace Doppar\Ripple\Exceptions;

final class ValidationException extends \RuntimeException
{
    /**
     * @param array<string, list<string>> $errors
     */
    public function __construct(private readonly array $errors, string $message = 'Validation failed')
    {
        parent::__construct($message);
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
