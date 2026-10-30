<?php

namespace Doppar\Ripple\Tests\Support;

use Doppar\Ripple\Delta\Op;
use Doppar\Ripple\VDom\HtmlParser;
use Doppar\Ripple\VDom\Node;

/**
 * Replays wire patches on a Node tree the way resources/js/src/patch.js replays them on the
 * DOM: ops run in order, and each path is resolved against the tree as it is at that moment.
 */
final class PatchApplier
{
    private HtmlParser $parser;

    public function __construct()
    {
        $this->parser = new HtmlParser();
    }

    /**
     * @param list<array<string, mixed>> $ops
     */
    public function apply(Node $root, array $ops): Node
    {
        foreach ($ops as $op) {
            $root = $this->applyOp($root, $op);
        }

        return $root;
    }

    /**
     * @param array<string, mixed> $op
     */
    private function applyOp(Node $root, array $op): Node
    {
        $path = (string) $op['p'];
        $segments = $path === '' ? [] : array_map('intval', explode('.', $path));

        switch ($op['o']) {
            case Op::SET_TEXT:
                $node = $this->nodeAt($root, $segments);
                $value = (string) ($op['v'] ?? '');
                if ($node->isElement()) {
                    $node->children = $value === '' ? [] : [new Node('#text', text: $value)];
                    break;
                }
                $node->text = $value;
                break;

            case Op::UPDATE_ATTRS:
                $node = $this->nodeAt($root, $segments);
                foreach ($op['a'] as $name => $value) {
                    if ($value === null || $value === false) {
                        unset($node->attrs[$name]);
                    } else {
                        $node->attrs[$name] = $value === true ? '' : (string) $value;
                    }
                }
                break;

            case Op::REPLACE:
                $replacement = $this->fromHtml((string) $op['h']);
                if ($segments === []) {
                    return $replacement;
                }
                $parent = $this->nodeAt($root, array_slice($segments, 0, -1));
                $parent->children[end($segments)] = $replacement;
                break;

            case Op::REMOVE:
                $parent = $this->nodeAt($root, array_slice($segments, 0, -1));
                $index = end($segments);
                if (isset($parent->children[$index])) {
                    array_splice($parent->children, $index, 1);
                }
                break;

            case Op::REORDER:
                $parent = $this->nodeAt($root, $segments);
                [$moved] = array_splice($parent->children, (int) $op['f'], 1);
                array_splice($parent->children, (int) $op['x'], 0, [$moved]);
                break;

            case Op::INSERT:
                $parent = $this->nodeAt($root, $segments);
                $child = $this->fromHtml((string) $op['h']);
                $at = $op['x'] ?? count($parent->children);
                array_splice($parent->children, min($at, count($parent->children)), 0, [$child]);
                break;
        }

        return $root;
    }

    /**
     * @param list<int> $segments
     */
    private function nodeAt(Node $root, array $segments): Node
    {
        $node = $root;
        foreach ($segments as $index) {
            if (! isset($node->children[$index])) {
                throw new \RuntimeException('Patch path does not exist: ' . implode('.', $segments));
            }
            $node = $node->children[$index];
        }

        return $node;
    }

    private function fromHtml(string $html): Node
    {
        // template.innerHTML keeps whitespace-only text nodes; HtmlParser::parse() trims them away.
        if (! str_contains($html, '<')) {
            return new Node('#text', text: html_entity_decode($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }

        return $this->parser->parse($html);
    }
}
