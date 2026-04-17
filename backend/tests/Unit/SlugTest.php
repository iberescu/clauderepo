<?php

namespace Tests\Unit;

use App\Support\Slug;
use PHPUnit\Framework\TestCase;

class SlugTest extends TestCase
{
    public function test_it_builds_a_url_safe_slug(): void
    {
        $this->assertSame(
            'rua-das-flores-lisbon-portugal',
            Slug::make('Rua das Flores', 'Lisbon', 'Portugal'),
        );
    }

    public function test_it_falls_back_when_input_is_empty(): void
    {
        $this->assertSame('unknown', Slug::make('', '', ''));
    }

    public function test_project_id_includes_timestamp_and_slug(): void
    {
        $at = new \DateTimeImmutable('2026-04-17T12:34:56+00:00');
        $this->assertSame(
            '20260417_123456_calle-mayor-madrid-spain',
            Slug::projectId('Calle Mayor', 'Madrid', 'Spain', $at),
        );
    }
}
