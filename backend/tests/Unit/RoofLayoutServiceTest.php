<?php

namespace Tests\Unit;

use App\DTOs\BuildingInsights;
use App\DTOs\RoofSegment;
use App\Services\RoofLayoutService;
use PHPUnit\Framework\TestCase;

class RoofLayoutServiceTest extends TestCase
{
    /** @return array<string, mixed> */
    private function panelDefaults(): array
    {
        return [
            'width_m' => 1.0,
            'height_m' => 2.0,
            'watt_peak' => 400,
            'setback_m' => 0.5,
            'row_gap_m' => 0.0,
            'col_gap_m' => 0.0,
        ];
    }

    public function test_it_fits_portrait_grid_in_a_plain_rectangle(): void
    {
        $segment = new RoofSegment(
            index: 1, pitchDegrees: 30, azimuthDegrees: 180,
            areaM2: 40.0, widthM: 6.0, heightM: 8.0,
            centerLat: 0, centerLng: 0,
            sunshineHoursPerYear: 1800, sunshineP50: 1800, shadePenalty: 0.1,
        );

        $insights = $this->wrap([$segment]);
        $layout = (new RoofLayoutService())->build($insights, $this->panelDefaults());

        // Usable area: 5m x 7m → portrait 1x2 gives cols=floor(5/1)=5, rows=floor(7/2)=3 → 15 panels
        // Landscape 2x1 gives cols=floor(5/2)=2, rows=floor(7/1)=7 → 14 panels
        // Portrait wins.
        $this->assertCount(1, $layout->segments);
        $first = $layout->segments[0];
        $this->assertSame('portrait', $first->orientation);
        $this->assertSame(15, $first->panelCount());
        $this->assertSame(5, $first->cols);
        $this->assertSame(3, $first->rows);
        $this->assertSame(15, $layout->totalPanels);
        $this->assertEqualsWithDelta(6.0, $layout->totalKwp, 0.01);
    }

    public function test_small_segments_are_skipped(): void
    {
        $tiny = new RoofSegment(
            index: 1, pitchDegrees: 30, azimuthDegrees: 180,
            areaM2: 4.0, widthM: 2.0, heightM: 2.0,
            centerLat: 0, centerLng: 0,
            sunshineHoursPerYear: 1800, sunshineP50: 1800, shadePenalty: 0.1,
        );

        $layout = (new RoofLayoutService())->build($this->wrap([$tiny]), $this->panelDefaults());
        $this->assertSame(0, $layout->totalPanels);
        $this->assertCount(0, $layout->segments);
    }

    public function test_layout_is_deterministic_for_the_same_inputs(): void
    {
        $segment = new RoofSegment(
            index: 1, pitchDegrees: 25, azimuthDegrees: 190,
            areaM2: 30.0, widthM: 5.0, heightM: 6.0,
            centerLat: 0, centerLng: 0,
            sunshineHoursPerYear: 1900, sunshineP50: 1900, shadePenalty: 0.12,
        );
        $service = new RoofLayoutService();
        $a = $service->build($this->wrap([$segment]), $this->panelDefaults());
        $b = $service->build($this->wrap([$segment]), $this->panelDefaults());

        $this->assertSame($a->totalPanels, $b->totalPanels);
        $this->assertSame($a->annualKwh, $b->annualKwh);
        $this->assertSame(
            array_map(fn ($p) => [$p->x, $p->y], $a->allPanels()),
            array_map(fn ($p) => [$p->x, $p->y], $b->allPanels()),
        );
    }

    /** @param list<RoofSegment> $segments */
    private function wrap(array $segments): BuildingInsights
    {
        $total = array_sum(array_map(fn ($s) => $s->areaM2, $segments));
        return new BuildingInsights(
            placeId: 'test', centerLat: 0, centerLng: 0,
            imageryQuality: 'HIGH', imageryDate: '2024-01-01',
            wholeRoofAreaM2: $total, groundAreaM2: $total,
            maxSunshineHoursPerYear: 1900, carbonOffsetKgPerMwh: 380,
            solarPotentialMaxPanels: 999, solarPotentialMaxAreaM2: $total,
            segments: $segments,
        );
    }
}
