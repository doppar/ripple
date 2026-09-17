<?php

namespace Doppar\Ripple\VDom;

/**
 * HTML to VDOM via DOMDocument.
 * ponytail: not a full HTML5 tokenizer; fine for component fragments.
 * Ceiling: weak on exotic SVG/math; upgrade to a dedicated lexer if needed.
 */
final class HtmlParser
{
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

        if (count($children) === 1) {
            return $children[0];
        }

        return new Node('div', ['data-ripple-fragment' => '1'], $children);
    }

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

        return new Node(strtolower($dom->tagName), $attrs, $children, key: $key);
    }
}
