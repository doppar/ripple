<?php

namespace Doppar\Ripple\Delta;

use Doppar\Ripple\VDom\Node;

/**
 * Tree-to-tree diff → Patch ops for the Ripple client.
 */
final class DiffEngine
{
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

        if ($old !== null && $new === null) {
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
        $max = max(count($oldKids), count($newKids));

        for ($i = 0; $i < $max; $i++) {
            $childPath = $path === '' ? (string) $i : $path . '.' . $i;
            $this->walk($oldKids[$i] ?? null, $newKids[$i] ?? null, $childPath, $patch);
        }
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
            if (!array_key_exists($key, $old) || $old[$key] !== $value) {
                $delta[$key] = $value;
            }
        }

        foreach ($old as $key => $_) {
            if (!array_key_exists($key, $new)) {
                $delta[$key] = null;
            }
        }

        // Internal markers never need client sync churn.
        unset($delta['data-ripple'], $delta['data-ripple-id'], $delta['data-ripple-name'], $delta['data-ripple-state']);

        return $delta;
    }

    private function parentPath(string $path): string
    {
        if ($path === '' || !str_contains($path, '.')) {
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
