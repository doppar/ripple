<?php

namespace Doppar\Ripple\Tests\Unit\VDom;

use Doppar\Ripple\Tests\TestCase;
use Doppar\Ripple\VDom\Node;

final class NodeTest extends TestCase
{
    public function test_element_and_text_helpers(): void
    {
        $el = new Node('div', ['class' => 'x']);
        $this->assertTrue($el->isElement());
        $this->assertFalse($el->isText());
        $this->assertSame('div', $el->type);

        $text = new Node('#text', [], [], 'hi');
        $this->assertTrue($text->isText());
        $this->assertFalse($text->isElement());
        $this->assertSame('hi', $text->text);
    }
}
