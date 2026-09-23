<?php

namespace Doppar\Ripple\Tests\Unit\Exceptions;

use Doppar\Ripple\Exceptions\MultipleRootsException;
use Doppar\Ripple\Exceptions\RedirectException;
use Doppar\Ripple\Exceptions\ValidationException;
use Doppar\Ripple\Tests\TestCase;
use Doppar\Ripple\VDom\HtmlParser;

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

    public function test_redirect_exception_preserves_spa_false(): void
    {
        $e = new RedirectException(['u' => '/y', 'spa' => false]);
        $this->assertSame(['u' => '/y', 'spa' => false], $e->redirect());
    }

    public function test_html_parser_rejects_multiple_roots(): void
    {
        $this->expectException(MultipleRootsException::class);
        $this->expectExceptionMessage('single root element');
        (new HtmlParser())->parse('<div>a</div><div>b</div>');
    }

    public function test_html_parser_allows_single_root_with_whitespace(): void
    {
        $node = (new HtmlParser())->parse("  \n<div class=\"x\">ok</div>\n  ");
        $this->assertSame('div', $node->type);
        $this->assertSame('x', $node->attrs['class'] ?? null);
    }
}
