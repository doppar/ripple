<?php

namespace Doppar\Ripple\Tests\Unit\Exceptions;

use Doppar\Ripple\Exceptions\RedirectException;
use Doppar\Ripple\Exceptions\ValidationException;
use Doppar\Ripple\Tests\TestCase;

final class ExceptionsTest extends TestCase
{
    public function test_validation_exception_exposes_errors(): void
    {
        $e = new ValidationException(['email' => ['required']]);
        $this->assertSame(['email' => ['required']], $e->errors());
        $this->assertSame('Validation failed', $e->getMessage());
    }

    public function test_redirect_exception_defaults_spa_true(): void
    {
        $e = new RedirectException(['u' => '/x']);
        $this->assertSame(['u' => '/x', 'spa' => true], $e->redirect());
    }
}
