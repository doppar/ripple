<?php

namespace Doppar\Ripple\Tests\Unit\Delta;

use Doppar\Ripple\Delta\DiffEngine;
use Doppar\Ripple\Delta\Op;
use Doppar\Ripple\Tests\TestCase;
use Doppar\Ripple\VDom\HtmlParser;

final class DiffEngineTest extends TestCase
{
    private HtmlParser $parser;
    private DiffEngine $diff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new HtmlParser();
        $this->diff = new DiffEngine();
    }

    public function test_emits_set_text_for_text_change(): void
    {
        $old = $this->parser->parse('<div><p>Count: 0</p><button type="button">+</button></div>');
        $new = $this->parser->parse('<div><p>Count: 1</p><button type="button">+</button></div>');

        $ops = $this->diff->diff($old, $new)->toArray();
        $setText = array_values(array_filter($ops, static fn (array $o) => ($o['o'] ?? '') === Op::SET_TEXT));

        $this->assertNotEmpty($setText);
        $this->assertSame('Count: 1', $setText[0]['v'] ?? null);
    }

    public function test_emits_update_attrs_for_class_change(): void
    {
        $old = $this->parser->parse('<div class="a"><span>x</span></div>');
        $new = $this->parser->parse('<div class="b"><span>x</span></div>');

        $ops = array_values(array_filter(
            $this->diff->diff($old, $new)->toArray(),
            static fn (array $o) => ($o['o'] ?? '') === Op::UPDATE_ATTRS,
        ));

        $this->assertNotEmpty($ops);
        $this->assertSame('b', $ops[0]['a']['class'] ?? null);
    }

    public function test_emits_remove_when_child_disappears(): void
    {
        $old = $this->parser->parse('<ul><li>a</li><li>b</li></ul>');
        $new = $this->parser->parse('<ul><li>a</li></ul>');

        $ops = array_values(array_filter(
            $this->diff->diff($old, $new)->toArray(),
            static fn (array $o) => ($o['o'] ?? '') === Op::REMOVE,
        ));

        $this->assertNotEmpty($ops);
    }

    public function test_empty_when_trees_match(): void
    {
        $tree = $this->parser->parse('<div><p>same</p></div>');

        $this->assertTrue($this->diff->diff($tree, $tree)->isEmpty());
    }

    public function test_emits_insert_when_child_added(): void
    {
        $old = $this->parser->parse('<ul><li>a</li></ul>');
        $new = $this->parser->parse('<ul><li>a</li><li>b</li></ul>');

        $ops = array_values(array_filter(
            $this->diff->diff($old, $new)->toArray(),
            static fn (array $o) => ($o['o'] ?? '') === Op::INSERT,
        ));

        $this->assertNotEmpty($ops);
    }

    public function test_emits_replace_when_tag_changes(): void
    {
        $old = $this->parser->parse('<div><span>x</span></div>');
        $new = $this->parser->parse('<div><strong>x</strong></div>');

        $ops = $this->diff->diff($old, $new)->toArray();
        $this->assertNotEmpty($ops);
        $kinds = array_map(static fn (array $o) => $o['o'] ?? '', $ops);
        $this->assertTrue(
            in_array(Op::REPLACE, $kinds, true)
            || in_array(Op::REMOVE, $kinds, true)
            || in_array(Op::INSERT, $kinds, true),
        );
    }
}
