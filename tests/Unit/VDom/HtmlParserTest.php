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

    public function test_wraps_bare_table_rows_in_an_implicit_tbody_like_a_browser(): void
    {
        $table = (new HtmlParser())->parse("<table>\n  <tr><td>a</td></tr>\n  <tr><td>b</td></tr>\n</table>");

        // Leading whitespace stays on the table; rows and the whitespace after them go in the tbody.
        $this->assertSame(['#text', 'tbody'], array_map(static fn ($n) => $n->type, $table->children));
        $tbody = $table->children[1];
        $this->assertSame(['tr', '#text', 'tr', '#text'], array_map(static fn ($n) => $n->type, $tbody->children));
    }

    public function test_leaves_explicit_table_sections_alone(): void
    {
        $table = (new HtmlParser())->parse('<table><thead><tr><th>h</th></tr></thead><tbody><tr><td>a</td></tr></tbody></table>');

        $this->assertSame(['thead', 'tbody'], array_map(static fn ($n) => $n->type, $table->children));
        $this->assertSame('tr', $table->children[1]->children[0]->type);
    }

    public function test_bare_rows_after_a_thead_get_their_own_tbody(): void
    {
        $table = (new HtmlParser())->parse('<table><thead><tr><th>h</th></tr></thead><tr><td>a</td></tr></table>');

        $this->assertSame(['thead', 'tbody'], array_map(static fn ($n) => $n->type, $table->children));
        $this->assertSame('tr', $table->children[1]->children[0]->type);
    }

    public function test_nested_tables_are_normalised_too(): void
    {
        $outer = (new HtmlParser())->parse('<table><tr><td><table><tr><td>x</td></tr></table></td></tr></table>');

        $inner = $outer->children[0]->children[0]->children[0]->children[0];
        $this->assertSame('table', $inner->type);
        $this->assertSame('tbody', $inner->children[0]->type);
    }
}
