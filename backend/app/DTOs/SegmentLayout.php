<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Per-roof-segment layout decision: how many rows/columns fit, in which
 * orientation, and the annual energy score used to compare orientations.
 */
final class SegmentLayout
{
    /** @param list<PanelPlacement> $panels */
    public function __construct(
        public readonly int    $segmentIndex,
        public readonly string $orientation,
        public readonly int    $rows,
        public readonly int    $cols,
        public readonly array  $panels,
        public readonly float  $annualKwh,
        public readonly float  $usedAreaM2,
    ) {
    }

    public function panelCount(): int
    {
        return count($this->panels);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'segment' => $this->segmentIndex,
            'orientation' => $this->orientation,
            'rows' => $this->rows,
            'cols' => $this->cols,
            'panel_count' => $this->panelCount(),
            'annual_kwh' => $this->annualKwh,
            'used_area_m2' => $this->usedAreaM2,
        ];
    }
}
