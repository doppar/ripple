<?php

namespace Doppar\Ripple\Tests\Unit\VDom;

use Doppar\Ripple\Tests\TestCase;
use Doppar\Ripple\VDom\HtmlParser;

final class HtmlParserTest extends TestCase
{
    public function test_parses_element_tree(): void
    {
        $node = (new HtmlParser())->parse('<div class="box"><span>hi</span></div>');

        $this->assertSame('div', $node->type);
        $this->assertSame('box', $node->attrs['class'] ?? null);
        $this->assertCount(1, $node->children);
        $this->assertSame('span', $node->children[0]->type);
        $this->assertSame('hi', $node->children[0]->children[0]->text ?? null);
    }

    public function test_parses_empty_string_as_text_node(): void
    {
        $node = (new HtmlParser())->parse('');

        $this->assertTrue($node->isText());
        $this->assertSame('', $node->text);
    }
}
