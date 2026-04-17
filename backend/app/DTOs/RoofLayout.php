<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Aggregate roof layout: the segment-by-segment decisions plus totals used by
 * downstream pricing and proposal generation.
 */
final class RoofLayout
{
    /** @param list<SegmentLayout> $segments */
    public function __construct(
        public readonly array  $segments,
        public readonly float  $panelWidthM,
        public readonly float  $panelHeightM,
        public readonly int    $panelWattPeak,
        public readonly float  $setbackM,
        public readonly int    $totalPanels,
        public readonly float  $totalKwp,
        public readonly float  $annualKwh,
        public readonly float  $aestheticScore,
    ) {
    }

    /** @return list<PanelPlacement> */
    public function allPanels(): array
    {
        $out = [];
        foreach ($this->segments as $s) {
            foreach ($s->panels as $p) {
                $out[] = $p;
            }
        }
        return $out;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'panel_width_m' => $this->panelWidthM,
            'panel_height_m' => $this->panelHeightM,
            'panel_watt_peak' => $this->panelWattPeak,
            'setback_m' => $this->setbackM,
            'total_panels' => $this->totalPanels,
            'total_kwp' => $this->totalKwp,
            'annual_kwh' => $this->annualKwh,
            'aesthetic_score' => $this->aestheticScore,
            'segments' => array_map(fn (SegmentLayout $s) => $s->toArray(), $this->segments),
        ];
    }
}
