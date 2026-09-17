<?php

namespace Doppar\Ripple\Tests\Unit\Delta;

use Doppar\Ripple\Delta\Op;
use Doppar\Ripple\Tests\TestCase;

final class OpTest extends TestCase
{
    public function test_to_array_uses_short_wire_keys(): void
    {
        $op = new Op(Op::SET_TEXT, '0.0', ['value' => 'Count: 1']);

        $this->assertSame([
            'o' => 't',
            'p' => '0.0',
            'v' => 'Count: 1',
        ], $op->toArray());
    }

    public function test_maps_html_attrs_and_index_keys(): void
    {
        $op = new Op(Op::INSERT, '', [
            'html' => '<li>x</li>',
            'index' => 1,
            'attrs' => ['class' => 'a'],
        ]);

        $this->assertSame([
            'o' => 'i',
            'p' => '',
            'h' => '<li>x</li>',
            'x' => 1,
            'a' => ['class' => 'a'],
        ], $op->toArray());
    }
}
