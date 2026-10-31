<?php

namespace Doppar\Ripple\Delta;

use Doppar\Ripple\VDom\Node;

/**
 * Tree-to-tree diff → Patch ops for the Ripple client.
 */
final class DiffEngine
{
    /**
     * Largest LCS table (old * new children) worth building; bigger lists diff positionally.
     *
     * @var int
     */
    private const LCS_CELL_LIMIT = 40000;

    /**
     * Diff two virtual DOM trees into a patch.
     *
     * @param ?Node $old
     * @param ?Node $new
     * @param string $path
     * @return Patch
     */
    public function diff(?Node $old, ?Node $new, string $path = ''): Patch
    {
        $patch = new Patch();
        $this->walk($old, $new, $path, $patch);

        return $patch;
    }

    /**
     * Compare two nodes recursively and append the resulting operations to the patch.
     *
     * @param ?Node $old
     * @param ?Node $new
     * @param string $path
     * @param Patch $patch
     * @return void
     */
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

        [$oldKids, $moves] = $this->reorderKeyed($oldKids, $newKids);
        foreach ($moves as [$from, $to]) {
            $patch->add(new Op(Op::REORDER, $path, ['from' => $from, 'index' => $to]));
        }

        $this->walkChildren($oldKids, $newKids, $path, $patch);
    }

    /**
     * Move keyed children so the keys shared by both lists appear in the new order.
     *
     * @param list<Node> $oldKids
     * @param list<Node> $newKids
     * @return array{list<Node>, list<array{int, int}>}
     */
    private function reorderKeyed(array $oldKids, array $newKids): array
    {
        $oldKeyed = $this->keyedSignatures($oldKids);
        $newKeyed = $this->keyedSignatures($newKids);

        if ($oldKeyed === null || $newKeyed === null) {
            return [$oldKids, []];
        }

        $inOld = array_flip($oldKeyed);
        $inNew = array_flip($newKeyed);
        $oldOrder = array_values(array_filter($oldKeyed, static fn (string $sig) => isset($inNew[$sig])));
        $newOrder = array_values(array_filter($newKeyed, static fn (string $sig) => isset($inOld[$sig])));

        if ($oldOrder === $newOrder) {
            return [$oldKids, []];
        }

        $rank = array_flip($newOrder);
        $stay = [];
        foreach ($this->longestIncreasing(array_map(static fn (string $sig) => $rank[$sig], $oldOrder)) as $r) {
            $stay[$newOrder[$r]] = true;
        }

        $firstStay = null;
        foreach ($newOrder as $sig) {
            if (isset($stay[$sig])) {
                $firstStay = $sig;
                break;
            }
        }

        $current = $oldKids;
        $moves = [];

        foreach ($newOrder as $i => $sig) {
            if (isset($stay[$sig])) {
                continue;
            }

            $from = $this->indexOfSignature($current, $sig);
            $anchor = $this->indexOfSignature($current, $i === 0 ? (string) $firstStay : $newOrder[$i - 1]);

            // Index of the anchor once the moved child has been taken out of the list.
            $anchor -= $from < $anchor ? 1 : 0;
            $to = $i === 0 ? $anchor : $anchor + 1;

            if ($from === $to) {
                continue;
            }

            [$node] = array_splice($current, $from, 1);
            array_splice($current, $to, 0, [$node]);
            $moves[] = [$from, $to];
        }

        return [$current, $moves];
    }

    /**
     * Get the signatures of the keyed children, or null when a key is repeated.
     *
     * @param list<Node> $kids
     * @return list<string>|null
     */
    private function keyedSignatures(array $kids): ?array
    {
        $signatures = [];

        foreach ($kids as $kid) {
            if ($kid->isText() || $kid->key === null) {
                continue;
            }

            $signature = $this->signature($kid);

            if (in_array($signature, $signatures, true)) {
                return null;
            }

            $signatures[] = $signature;
        }

        return $signatures;
    }

    /**
     * Find the index of the child with the given signature.
     *
     * @param list<Node> $kids
     * @param string $signature
     * @return int
     */
    private function indexOfSignature(array $kids, string $signature): int
    {
        foreach ($kids as $index => $kid) {
            if (! $kid->isText() && $this->signature($kid) === $signature) {
                return $index;
            }
        }

        throw new \LogicException("Keyed child [{$signature}] is missing from the list.");
    }

    /**
     * Get the longest strictly increasing subsequence of a list of integers.
     *
     * @param list<int> $sequence
     * @return list<int>
     */
    private function longestIncreasing(array $sequence): array
    {
        $tails = [];
        $previous = [];

        foreach ($sequence as $i => $value) {
            $low = 0;
            $high = count($tails);

            while ($low < $high) {
                $mid = ($low + $high) >> 1;

                if ($sequence[$tails[$mid]] < $value) {
                    $low = $mid + 1;
                } else {
                    $high = $mid;
                }
            }

            $previous[$i] = $low > 0 ? $tails[$low - 1] : -1;
            $tails[$low] = $i;
        }

        $result = [];
        $k = $tails === [] ? -1 : end($tails);

        while ($k >= 0) {
            array_unshift($result, $sequence[$k]);
            $k = $previous[$k];
        }

        return $result;
    }

    /**
     * Diff two child lists and append the resulting operations to the patch.
     *
     * Children that survive on both sides (same tag, same key) are
     * aligned with a longest-common-subsequence match and diffed in place, so inserting or
     * removing one node in the middle does not make every following sibling look changed
     * (which would replace live nodes, including an input the user is typing in).
     *
     * Ops are emitted in document order and every path is valid at the moment the op runs:
     * removals inside a gap go highest index first, insertions lowest index first.
     *
     * @param list<Node> $oldKids
     * @param list<Node> $newKids
     * @param string $path
     * @param Patch $patch
     * @return void
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
     * Diff the unmatched children that lie between two anchors.
     *
     * Old children [$oi, $ai) became new children [$nj, $aj). Pair them up positionally,
     * then remove the surplus old nodes or insert the surplus new ones.
     *
     * @param list<Node> $oldKids
     * @param list<Node> $newKids
     * @param int $oi
     * @param int $ai
     * @param int $nj
     * @param int $aj
     * @param string $path
     * @param Patch $patch
     * @return void
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
     * Find the matched child index pairs used as diff anchors.
     *
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
     * Compute the longest common subsequence of two signature lists as index pairs.
     *
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
     * Build the matching signature of a node from its type and key.
     *
     * Two children can be diffed in place when their signatures match.
     *
     * @param Node $node
     * @return string
     */
    private function signature(Node $node): string
    {
        if ($node->isText()) {
            return '#text';
        }

        return $node->key !== null ? $node->type . '#' . $node->key : $node->type;
    }

    /**
     * Append a child index to a dot-separated node path.
     *
     * @param string $path
     * @param int $index
     * @return string
     */
    private function childPath(string $path, int $index): string
    {
        return $path === '' ? (string) $index : $path . '.' . $index;
    }

    /**
     * Compute the attribute changes between two attribute sets.
     *
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

    /**
     * Get the parent path of a dot-separated node path.
     *
     * @param string $path
     * @return string
     */
    private function parentPath(string $path): string
    {
        if ($path === '' || ! str_contains($path, '.')) {
            return '';
        }

        return substr($path, 0, (int) strrpos($path, '.'));
    }

    /**
     * Get the child index from the last segment of a node path.
     *
     * @param string $path
     * @return int
     */
    private function indexOf(string $path): int
    {
        if ($path === '') {
            return 0;
        }

        $parts = explode('.', $path);

        return (int) end($parts);
    }

    /**
     * Render a virtual DOM node back to an HTML string.
     *
     * @param Node $node
     * @return string
     */
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
