<?php

namespace Doppar\Ripple\VDom;

use Doppar\Ripple\Exceptions\MultipleRootsException;

/**
 * HTML to VDOM via DOMDocument.
 * ponytail: not a full HTML5 tokenizer; fine for component fragments.
 * Ceiling: weak on exotic SVG/math; upgrade to a dedicated lexer if needed.
 */
final class HtmlParser
{
    /**
     * Parse an HTML fragment into a single virtual DOM root node.
     *
     * @param string $html
     * @return Node
     */
    public function parse(string $html): Node
    {
        $html = trim($html);
        if ($html === '') {
            return new Node('#text', text: '');
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $wrapped = '<?xml encoding="UTF-8"><div id="__ripple_root__">' . $html . '</div>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('__ripple_root__');
        if (! $root) {
            return new Node('#text', text: $html);
        }

        $children = [];
        foreach ($root->childNodes as $child) {
            $node = $this->fromDom($child);
            if ($node !== null) {
                $children[] = $node;
            }
        }

        $elements = array_values(array_filter(
            $children,
            static fn (Node $n) => $n->isElement(),
        ));

        if (count($elements) > 1) {
            throw new MultipleRootsException(array_map(
                static fn (Node $n) => $n->type,
                $elements,
            ));
        }

        if (count($elements) === 1) {
            return $elements[0];
        }

        return $children[0] ?? new Node('#text', text: '');
    }

    /**
     * Convert a DOM node into a virtual DOM node, or null when it should be skipped.
     *
     * @param \DOMNode $dom
     * @return ?Node
     */
    private function fromDom(\DOMNode $dom): ?Node
    {
        if ($dom instanceof \DOMText) {
            $text = $dom->textContent;
            if ($text === '') {
                return null;
            }

            return new Node('#text', text: $text);
        }

        if (! $dom instanceof \DOMElement) {
            return null;
        }

        $attrs = [];
        if ($dom->hasAttributes()) {
            foreach ($dom->attributes as $attr) {
                $attrs[$attr->name] = $attr->value;
            }
        }

        $key = isset($attrs['data-ripple-key']) ? (string) $attrs['data-ripple-key'] : null;

        $children = [];
        foreach ($dom->childNodes as $child) {
            $node = $this->fromDom($child);
            if ($node !== null) {
                $children[] = $node;
            }
        }

        $tag = strtolower($dom->tagName);
        if ($tag === 'table') {
            $children = $this->withImplicitTbody($children);
        }

        return new Node($tag, $attrs, $children, key: $key);
    }

    /**
     * Wrap bare table rows in an implicit tbody node.
     *
     * Browsers wrap bare <tr> children of a <table> in an implicit <tbody>; libxml does not.
     * Mirror the browser tree so diff paths line up with the live DOM. Whitespace before the
     * first <tr> stays on the table, everything after it (including trailing whitespace) goes
     * into the tbody, exactly like the HTML5 tree builder.
     *
     * @param list<Node> $children
     * @return list<Node>
     */
    private function withImplicitTbody(array $children): array
    {
        $sections = ['thead', 'tbody', 'tfoot', 'caption', 'colgroup'];
        $out = [];
        $body = null;

        foreach ($children as $child) {
            if ($body === null) {
                if ($child->type === 'tr') {
                    $body = new Node('tbody', [], [$child]);
                    $out[] = $body;
                    continue;
                }

                $out[] = $child;
                continue;
            }

            if (in_array($child->type, $sections, true)) {
                $body = null;
                $out[] = $child;
                continue;
            }

            $body->children[] = $child;
        }

        return $out;
    }
}
