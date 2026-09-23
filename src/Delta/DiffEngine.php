<?php

namespace Doppar\Ripple\Delta;

use Doppar\Ripple\VDom\Node;

/**
 * Tree-to-tree diff → Patch ops for the Ripple client.
 */
final class DiffEngine
{
    /** Largest LCS table (old * new children) worth building; bigger lists diff positionally. */
    private const LCS_CELL_LIMIT = 40000;

    public function diff(?Node $old, ?Node $new, string $path = ''): Patch
    {
        $patch = new Patch();
        $this->walk($old, $new, $path, $patch);

        return $patch;
    }

    private function walk(?Node $old, ?Node $new, string $path, Patch $patch): void
    {
        if ($old === null && $new === null) {
            return;
        }

        if ($old === null && $new !== null) {
            $patch->add(new Op(Op::INSERT, $this->parentPath($path), [
                'index' => $this->indexOf($path),
                'html' => $this->toHtml($new),
            ]));

            return;
        }

        if ($new === null) {
            $patch->add(new Op(Op::REMOVE, $path));

            return;
        }

        /** @var Node $old */
        /** @var Node $new */

        if ($old->isText() || $new->isText()) {
            if ($old->type !== $new->type || ($old->text ?? '') !== ($new->text ?? '')) {
                if ($old->isText() && $new->isText()) {
                    $patch->add(new Op(Op::SET_TEXT, $path, ['value' => $new->text ?? '']));
                } else {
                    $patch->add(new Op(Op::REPLACE, $path, ['html' => $this->toHtml($new)]));
                }
            }

            return;
        }

        if ($old->type !== $new->type) {
            $patch->add(new Op(Op::REPLACE, $path, ['html' => $this->toHtml($new)]));

            return;
        }

        $attrDelta = $this->attrDelta($old->attrs, $new->attrs);
        if ($attrDelta !== []) {
            $patch->add(new Op(Op::UPDATE_ATTRS, $path, ['attrs' => $attrDelta]));
        }

        $oldKids = $old->children;
        $newKids = $new->children;

        // Empty ↔ single text: set textContent on the element so the client
        // replaces any live children (INSERT would duplicate text nodes).
        if ($oldKids === [] && count($newKids) === 1 && $newKids[0]->isText()) {
            $patch->add(new Op(Op::SET_TEXT, $path, ['value' => $newKids[0]->text ?? '']));

            return;
        }
        if (count($oldKids) === 1 && $oldKids[0]->isText() && $newKids === []) {
            $patch->add(new Op(Op::SET_TEXT, $path, ['value' => '']));

            return;
        }

        $this->walkChildren($oldKids, $newKids, $path, $patch);
    }

    /**
     * Diff two child lists. Children that survive on both sides (same tag, same key) are
     * aligned with a longest-common-subsequence match and diffed in place, so inserting or
     * removing one node in the middle does not make every following sibling look changed
     * (which would replace live nodes, including an input the user is typing in).
     *
     * Ops are emitted in document order and every path is valid at the moment the op runs:
     * removals inside a gap go highest index first, insertions lowest index first.
     *
     * @param list<Node> $oldKids
     * @param list<Node> $newKids
     */
    private function walkChildren(array $oldKids, array $newKids, string $path, Patch $patch): void
    {
        $oldCount = count($oldKids);
        $newCount = count($newKids);

        $oi = 0;
        $nj = 0;

        foreach ([...$this->anchors($oldKids, $newKids), [$oldCount, $newCount]] as [$ai, $aj]) {
            $this->walkGap($oldKids, $newKids, $oi, $ai, $nj, $aj, $path, $patch);

            if ($ai < $oldCount) {
                $this->walk($oldKids[$ai], $newKids[$aj], $this->childPath($path, $aj), $patch);
            }

            $oi = $ai + 1;
            $nj = $aj + 1;
        }
    }

    /**
     * Old children [$oi, $ai) became new children [$nj, $aj). Pair them up positionally,
     * then remove the surplus old nodes or insert the surplus new ones.
     *
     * @param list<Node> $oldKids
     * @param list<Node> $newKids
     */
    private function walkGap(array $oldKids, array $newKids, int $oi, int $ai, int $nj, int $aj, string $path, Patch $patch): void
    {
        $removed = $ai - $oi;
        $added = $aj - $nj;
        $paired = min($removed, $added);

        for ($t = 0; $t < $paired; $t++) {
            $this->walk($oldKids[$oi + $t], $newKids[$nj + $t], $this->childPath($path, $nj + $t), $patch);
        }

        for ($t = $removed - 1; $t >= $paired; $t--) {
            $this->walk($oldKids[$oi + $t], null, $this->childPath($path, $nj + $t), $patch);
        }

        for ($t = $paired; $t < $added; $t++) {
            $this->walk(null, $newKids[$nj + $t], $this->childPath($path, $nj + $t), $patch);
        }
    }

    /**
     * Matched (oldIndex, newIndex) pairs, increasing on both sides. Common head and tail are
     * matched directly; the middle uses an LCS table, skipped when it would be too large
     * (the middle then falls back to positional pairing).
     *
     * @param list<Node> $oldKids
     * @param list<Node> $newKids
     * @return list<array{int, int}>
     */
    private function anchors(array $oldKids, array $newKids): array
    {
        $old = array_map($this->signature(...), $oldKids);
        $new = array_map($this->signature(...), $newKids);
        $n = count($old);
        $m = count($new);

        $head = 0;
        while ($head < $n && $head < $m && $old[$head] === $new[$head]) {
            $head++;
        }

        $tail = 0;
        while ($tail < $n - $head && $tail < $m - $head && $old[$n - 1 - $tail] === $new[$m - 1 - $tail]) {
            $tail++;
        }

        $pairs = [];
        for ($i = 0; $i < $head; $i++) {
            $pairs[] = [$i, $i];
        }

        $rows = $n - $head - $tail;
        $cols = $m - $head - $tail;

        if ($rows > 0 && $cols > 0 && $rows * $cols <= self::LCS_CELL_LIMIT) {
            foreach ($this->lcs(array_slice($old, $head, $rows), array_slice($new, $head, $cols)) as [$i, $j]) {
                $pairs[] = [$head + $i, $head + $j];
            }
        }

        for ($k = $tail; $k > 0; $k--) {
            $pairs[] = [$n - $k, $m - $k];
        }

        return $pairs;
    }

    /**
     * @param list<string> $old
     * @param list<string> $new
     * @return list<array{int, int}>
     */
    private function lcs(array $old, array $new): array
    {
        $n = count($old);
        $m = count($new);

        $len = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $len[$i][$j] = $old[$i] === $new[$j]
                    ? $len[$i + 1][$j + 1] + 1
                    : max($len[$i + 1][$j], $len[$i][$j + 1]);
            }
        }

        $pairs = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($old[$i] === $new[$j]) {
                $pairs[] = [$i, $j];
                $i++;
                $j++;
            } elseif ($len[$i + 1][$j] >= $len[$i][$j + 1]) {
                $i++;
            } else {
                $j++;
            }
        }

        return $pairs;
    }

    /**
     * Two children can be diffed in place when their signatures match.
     */
    private function signature(Node $node): string
    {
        if ($node->isText()) {
            return '#text';
        }

        return $node->key !== null ? $node->type . '#' . $node->key : $node->type;
    }

    private function childPath(string $path, int $index): string
    {
        return $path === '' ? (string) $index : $path . '.' . $index;
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     * @return array<string, mixed>
     */
    private function attrDelta(array $old, array $new): array
    {
        $delta = [];

        foreach ($new as $key => $value) {
            if (! array_key_exists($key, $old) || $old[$key] !== $value) {
                $delta[$key] = $value;
            }
        }

        foreach ($old as $key => $_) {
            if (! array_key_exists($key, $new)) {
                $delta[$key] = null;
            }
        }

        // Internal markers never need client sync churn.
        unset($delta['data-ripple'], $delta['data-ripple-id'], $delta['data-ripple-name'], $delta['data-ripple-state']);

        return $delta;
    }

    private function parentPath(string $path): string
    {
        if ($path === '' || ! str_contains($path, '.')) {
            return '';
        }

        return substr($path, 0, (int) strrpos($path, '.'));
    }

    private function indexOf(string $path): int
    {
        if ($path === '') {
            return 0;
        }

        $parts = explode('.', $path);

        return (int) end($parts);
    }

    public function toHtml(Node $node): string
    {
        if ($node->isText()) {
            return htmlspecialchars($node->text ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $attrs = '';
        foreach ($node->attrs as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            if ($value === true) {
                $attrs .= ' ' . $name;
                continue;
            }
            $attrs .= ' ' . $name . '="' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        $inner = '';
        foreach ($node->children as $child) {
            $inner .= $this->toHtml($child);
        }

        $void = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];
        if (in_array($node->type, $void, true)) {
            return '<' . $node->type . $attrs . '>';
        }

        return '<' . $node->type . $attrs . '>' . $inner . '</' . $node->type . '>';
    }
}
