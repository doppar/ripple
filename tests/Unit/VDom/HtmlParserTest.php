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

    public function test_parses_nested_siblings_and_attrs(): void
    {
        $node = (new HtmlParser())->parse('<div id="root"><p class="a">one</p><p class="b">two</p></div>');

        $this->assertSame('root', $node->attrs['id'] ?? null);
        $this->assertCount(2, $node->children);
        $this->assertSame('a', $node->children[0]->attrs['class'] ?? null);
        $this->assertSame('two', $node->children[1]->children[0]->text ?? null);
    }

    public function test_parses_self_closing_and_voidish(): void
    {
        $node = (new HtmlParser())->parse('<div><img src="x.png" alt="x"><br></div>');
        $this->assertSame('div', $node->type);
        $this->assertGreaterThanOrEqual(1, count($node->children));
    }
}
