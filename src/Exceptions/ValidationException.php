<?php

namespace Doppar\Ripple\Exceptions;

final class ValidationException extends \RuntimeException
{
    /**
     * Create a new validation exception with the given field errors.
     *
     * @param array<string, list<string>> $errors
     * @param string $message
     */
    public function __construct(private readonly array $errors, string $message = 'Validation failed')
    {
        parent::__construct($message);
    }

    /**
     * Get the validation errors keyed by field name.
     *
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
