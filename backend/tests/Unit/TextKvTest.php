<?php

namespace Tests\Unit;

use App\Support\TextKv;
use PHPUnit\Framework\TestCase;

class TextKvTest extends TestCase
{
    public function test_it_renders_flat_key_value_pairs(): void
    {
        $out = TextKv::render(['street' => 'Rua das Flores', 'confidence' => 0.9, 'ok' => true]);
        $this->assertStringContainsString("street: Rua das Flores\n", $out);
        $this->assertStringContainsString("confidence: 0.9\n", $out);
        $this->assertStringContainsString("ok: true\n", $out);
    }

    public function test_parse_round_trips_scalar_keys(): void
    {
        $out = TextKv::render(['a' => 1, 'b' => 'two']);
        $parsed = TextKv::parse($out);
        $this->assertSame(['a' => '1', 'b' => 'two'], $parsed);
    }
}
