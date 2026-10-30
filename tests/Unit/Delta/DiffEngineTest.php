<?php

namespace Doppar\Ripple\Tests\Unit\Delta;

use Doppar\Ripple\Delta\DiffEngine;
use Doppar\Ripple\Delta\Op;
use Doppar\Ripple\Tests\Support\PatchApplier;
use Doppar\Ripple\Tests\TestCase;
use Doppar\Ripple\VDom\HtmlParser;
use Doppar\Ripple\VDom\Node;

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

    public function test_empty_element_to_text_uses_set_text_on_element(): void
    {
        $old = $this->parser->parse('<p class="err"></p>');
        $new = $this->parser->parse('<p class="err">Required</p>');

        $ops = $this->diff->diff($old, $new)->toArray();
        $this->assertCount(1, $ops);
        $this->assertSame(Op::SET_TEXT, $ops[0]['o'] ?? null);
        $this->assertSame('Required', $ops[0]['v'] ?? null);
        // Path is the element itself ('' for a single root), not a child INSERT.
        $this->assertSame('', $ops[0]['p'] ?? null);
    }

    public function test_text_to_empty_element_clears_via_set_text(): void
    {
        $old = $this->parser->parse('<p class="err">Required</p>');
        $new = $this->parser->parse('<p class="err"></p>');

        $ops = $this->diff->diff($old, $new)->toArray();
        $this->assertCount(1, $ops);
        $this->assertSame(Op::SET_TEXT, $ops[0]['o'] ?? null);
        $this->assertSame('', $ops[0]['v'] ?? null);
    }

    public function test_conditional_error_block_inserts_once_then_stable(): void
    {
        $without = $this->parser->parse('<div><input></div>');
        $with = $this->parser->parse('<div><input><p class="text-danger">The Email field is required</p></div>');

        $first = $this->diff->diff($without, $with)->toArray();
        $inserts = array_values(array_filter($first, static fn (array $o) => ($o['o'] ?? '') === Op::INSERT));
        $this->assertNotEmpty($inserts);

        $again = $this->diff->diff($with, $with)->toArray();
        $this->assertSame([], $again);
        $this->assertSame(
            [],
            array_values(array_filter($again, static fn (array $o) => ($o['o'] ?? '') === Op::INSERT)),
        );
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

    public function test_removals_are_emitted_highest_index_first(): void
    {
        $old = $this->parser->parse('<p><i>1</i><b>2</b><u>3</u><s>4</s>tail</p>');
        $new = $this->parser->parse('<p><i>1</i></p>');

        $removePaths = array_values(array_map(
            static fn (array $o) => $o['p'],
            array_filter($this->diff->diff($old, $new)->toArray(), static fn (array $o) => $o['o'] === Op::REMOVE),
        ));

        $this->assertSame(['4', '3', '2', '1'], $removePaths);
        $this->assertPatchesTo($old, $new);
    }

    public function test_inserting_a_block_in_the_middle_does_not_replace_following_siblings(): void
    {
        $before = '<form>'
            . '<label>Name</label><input type="text" name="name">'
            . '<label>Email</label><input type="text" name="email">'
            . '<button>Save</button>'
            . '</form>';
        $after = '<form>'
            . '<label>Name</label><input type="text" name="name"><p class="err">Too short</p>'
            . '<label>Email</label><input type="text" name="email">'
            . '<button>Save</button>'
            . '</form>';

        $old = $this->parser->parse($before);
        $new = $this->parser->parse($after);
        $ops = $this->diff->diff($old, $new)->toArray();

        $this->assertSame([Op::INSERT], array_values(array_unique(array_column($ops, 'o'))));
        $this->assertPatchesTo($old, $new);

        // ...and the same when the block goes away again.
        $this->assertSame(
            [Op::REMOVE],
            array_values(array_unique(array_column($this->diff->diff($new, $old)->toArray(), 'o'))),
        );
        $this->assertPatchesTo($new, $old);
    }

    public function test_keyed_children_align_by_key(): void
    {
        $old = $this->parser->parse('<ul><li data-ripple-key="a">A</li><li data-ripple-key="b">B</li><li data-ripple-key="c">C</li></ul>');
        $new = $this->parser->parse('<ul><li data-ripple-key="b">B</li><li data-ripple-key="c">C</li></ul>');

        $ops = $this->diff->diff($old, $new)->toArray();

        $this->assertSame([['o' => Op::REMOVE, 'p' => '0']], $ops);
        $this->assertPatchesTo($old, $new);
    }

    public function test_table_rows_patch_through_the_implicit_tbody(): void
    {
        $old = $this->parser->parse('<table><tr><td>a</td><td>0</td></tr><tr><td>b</td><td>0</td></tr></table>');
        $new = $this->parser->parse('<table><tr><td>a</td><td>1</td></tr><tr><td>b</td><td>0</td></tr></table>');

        $ops = $this->diff->diff($old, $new)->toArray();

        // table > tbody > tr > td > text: the browser DOM has the tbody, so the path must too.
        $this->assertSame([['o' => Op::SET_TEXT, 'p' => '0.0.1.0', 'v' => '1']], $ops);
    }

    public function test_huge_child_lists_still_patch_correctly(): void
    {
        $rows = static fn (int $from, int $to): string => implode('', array_map(
            static fn (int $i) => '<p class="r' . ($i % 3) . '">row ' . $i . '</p>',
            range($from, $to),
        ));

        // 300 x 300 children is over the LCS table limit, so the middle diffs positionally.
        $old = $this->parser->parse('<div>' . $rows(0, 299) . '</div>');
        $new = $this->parser->parse('<div><h2>new</h2>' . $rows(5, 304) . '</div>');

        $this->assertPatchesTo($old, $new);
    }

    public function test_random_edits_always_patch_to_the_target_tree(): void
    {
        mt_srand(20260920);

        for ($round = 0; $round < 400; $round++) {
            $old = $this->randomElement(0);
            $new = $this->mutate($this->cloneNode($old), 0);

            $this->assertPatchesTo($old, $new, "round {$round}");
        }
    }

    public function test_reversing_keyed_children_only_moves_them(): void
    {
        $old = $this->parser->parse('<ul><li data-ripple-key="1">A</li><li data-ripple-key="2">B</li><li data-ripple-key="3">C</li></ul>');
        $new = $this->parser->parse('<ul><li data-ripple-key="3">C</li><li data-ripple-key="2">B</li><li data-ripple-key="1">A</li></ul>');

        $ops = $this->diff->diff($old, $new)->toArray();

        $this->assertNotEmpty($ops);
        $this->assertSame([Op::REORDER], array_values(array_unique(array_column($ops, 'o'))), json_encode($ops));
        $this->assertPatchesTo($old, $new);
    }

    public function test_moving_one_keyed_child_costs_one_move(): void
    {
        $old = $this->parser->parse('<ul><li data-ripple-key="1">A</li><li data-ripple-key="2">B</li><li data-ripple-key="3">C</li><li data-ripple-key="4">D</li></ul>');
        $new = $this->parser->parse('<ul><li data-ripple-key="1">A</li><li data-ripple-key="3">C</li><li data-ripple-key="4">D</li><li data-ripple-key="2">B</li></ul>');

        $ops = $this->diff->diff($old, $new)->toArray();

        $this->assertCount(1, $ops, json_encode($ops));
        $this->assertSame(Op::REORDER, $ops[0]['o']);
        $this->assertSame(1, $ops[0]['f']);
        $this->assertSame(3, $ops[0]['x']);
        $this->assertPatchesTo($old, $new);
    }

    public function test_reorder_with_inserts_removes_and_edits_converges(): void
    {
        $old = $this->parser->parse('<ul><li data-ripple-key="1">A</li><li data-ripple-key="2">B</li><li data-ripple-key="3">C</li></ul>');
        $new = $this->parser->parse('<ul><li data-ripple-key="3">C edited</li><li data-ripple-key="4">D</li><li data-ripple-key="1">A</li></ul>');

        $this->assertPatchesTo($old, $new);
    }

    public function test_unchanged_key_order_emits_no_moves(): void
    {
        $old = $this->parser->parse('<ul><li data-ripple-key="1">A</li><li data-ripple-key="2">B</li><li data-ripple-key="3">C</li></ul>');
        $new = $this->parser->parse('<ul><li data-ripple-key="1">A</li><li data-ripple-key="3">C</li></ul>');

        $ops = $this->diff->diff($old, $new)->toArray();

        $this->assertNotContains(Op::REORDER, array_column($ops, 'o'));
        $this->assertPatchesTo($old, $new);
    }

    public function test_repeated_keys_are_never_moved_but_still_converge(): void
    {
        $old = $this->parser->parse('<ul><li data-ripple-key="1">A</li><li data-ripple-key="1">B</li><li data-ripple-key="2">C</li></ul>');
        $new = $this->parser->parse('<ul><li data-ripple-key="2">C</li><li data-ripple-key="1">A</li><li data-ripple-key="1">B</li></ul>');

        $ops = $this->diff->diff($old, $new)->toArray();

        $this->assertNotContains(Op::REORDER, array_column($ops, 'o'));
        $this->assertPatchesTo($old, $new);
    }

    public function test_reordering_keyed_rows_inside_nested_markup_converges(): void
    {
        $old = $this->parser->parse('<div><h2>List</h2><ul><li data-ripple-key="a"><b>1</b></li><li data-ripple-key="b"><b>2</b></li></ul><p>end</p></div>');
        $new = $this->parser->parse('<div><h2>List</h2><ul><li data-ripple-key="b"><b>2</b></li><li data-ripple-key="a"><b>9</b></li></ul><p>end</p></div>');

        $this->assertPatchesTo($old, $new);
    }

    public function test_random_keyed_reorders_with_whitespace_always_converge(): void
    {
        mt_srand(20261030);

        for ($round = 0; $round < 300; $round++) {
            $pool = range(1, mt_rand(1, 9));
            shuffle($pool);
            $before = array_slice($pool, 0, mt_rand(0, count($pool)));
            shuffle($pool);
            $after = array_slice($pool, 0, mt_rand(0, count($pool)));

            $build = static function (array $keys): string {
                $rows = '';
                foreach ($keys as $key) {
                    $rows .= "\n  <li data-ripple-key=\"{$key}\"><span>row {$key}</span></li>";
                }

                return '<div><ul>' . $rows . "\n</ul><p>tail</p></div>";
            };

            $this->assertPatchesTo(
                $this->parser->parse($build($before)),
                $this->parser->parse($build($after)),
                'round ' . $round . ' ' . json_encode([$before, $after]),
            );
        }
    }

    private function assertPatchesTo(Node $old, Node $new, string $message = ''): void
    {
        $ops = $this->diff->diff($old, $new)->toArray();
        $applied = (new PatchApplier())->apply($this->cloneNode($old), $ops);

        $this->assertSame(
            $this->diff->toHtml($new),
            $this->diff->toHtml($applied),
            $message . ' ops=' . json_encode($ops),
        );
    }

    /** Tags whose children may only be text, so generated markup stays valid HTML. */
    private const TEXT_ONLY = ['p', 'label', 'li'];

    private function randomElement(int $depth): Node
    {
        $tag = match (mt_rand(0, 4)) {
            0 => 'input',
            1 => self::TEXT_ONLY[mt_rand(0, 2)],
            default => ['div', 'section'][mt_rand(0, 1)],
        };
        $attrs = mt_rand(0, 2) === 0 ? ['class' => 'c' . mt_rand(0, 2)] : [];
        if (mt_rand(0, 5) === 0) {
            $attrs['id'] = 'i' . mt_rand(0, 3);
        }

        if ($tag === 'input') {
            return new Node($tag, $attrs);
        }

        $textOnly = in_array($tag, self::TEXT_ONLY, true);
        $children = [];
        if ($depth < 3) {
            for ($n = mt_rand(0, 5); $n > 0; $n--) {
                $children[] = $this->randomChild($depth + 1, $textOnly);
            }
        }

        return new Node($tag, $attrs, $children, key: $tag === 'li' && mt_rand(0, 1) ? 'k' . mt_rand(0, 6) : null);
    }

    private function randomChild(int $depth, bool $textOnly = false): Node
    {
        if ($textOnly || mt_rand(0, 3) === 0) {
            return new Node('#text', text: ['a', 'b', "\n  ", 'row 1', 'x < y'][mt_rand(0, 4)]);
        }

        return $this->randomElement($depth);
    }

    private function mutate(Node $node, int $depth): Node
    {
        if ($node->isText() || $node->type === 'input') {
            return $node;
        }

        $textOnly = in_array($node->type, self::TEXT_ONLY, true);

        if (mt_rand(0, 6) === 0) {
            $node->attrs = mt_rand(0, 1) ? ['class' => 'c' . mt_rand(0, 2)] : [];
        }

        foreach ($node->children as $i => $child) {
            $roll = mt_rand(0, 9);
            if ($roll === 0) {
                unset($node->children[$i]);
            } elseif ($roll === 1) {
                $node->children[$i] = $this->randomChild($depth + 1, $textOnly);
            } elseif ($roll === 2 && $child->isText()) {
                $child->text = 'changed ' . mt_rand(0, 3);
            } else {
                $node->children[$i] = $this->mutate($child, $depth + 1);
            }
        }
        $node->children = array_values($node->children);

        for ($n = mt_rand(0, 2); $n > 0 && $depth < 3; $n--) {
            array_splice($node->children, mt_rand(0, count($node->children)), 0, [$this->randomChild($depth + 1, $textOnly)]);
        }

        return $node;
    }

    private function cloneNode(Node $node): Node
    {
        return new Node(
            $node->type,
            $node->attrs,
            array_map(fn (Node $child) => $this->cloneNode($child), $node->children),
            $node->text,
            $node->key,
        );
    }
}
