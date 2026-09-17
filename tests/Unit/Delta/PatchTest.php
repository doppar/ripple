<?php

namespace Doppar\Ripple\Tests\Unit\Delta;

use Doppar\Ripple\Delta\Op;
use Doppar\Ripple\Delta\Patch;
use Doppar\Ripple\Tests\TestCase;

final class PatchTest extends TestCase
{
    public function test_patch_collects_ops(): void
    {
        $patch = new Patch();
        $this->assertTrue($patch->isEmpty());

        $patch->add(new Op(Op::SET_TEXT, '0', ['value' => 'a']));
        $patch->add(new Op(Op::REMOVE, '1'));

        $this->assertFalse($patch->isEmpty());
        $this->assertCount(2, $patch->ops());
        $this->assertSame('t', $patch->toArray()[0]['o']);
        $this->assertSame('x', $patch->toArray()[1]['o']);
    }
}
