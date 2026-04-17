<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\BuildingInsights;
use App\DTOs\PanelPlacement;
use App\DTOs\RoofLayout;
use App\DTOs\RoofSegment;
use App\DTOs\SegmentLayout;

/**
 * Deterministic, geometry-driven panel layout. For each roof segment the
 * service tries both portrait and landscape panel orientations, computes how
 * many panels fit inside the rectangle minus setbacks, estimates annual kWh
 * and keeps the winning orientation per segment.
 *
 * The algorithm is intentionally conservative: no bin-packing heuristics, no
 * diagonal placement. The goal is reproducibility and plausibility for sales
 * proposals, not engineering-grade optimisation.
 */
final class RoofLayoutService
{
    private const PERFORMANCE_RATIO = 0.80;
    private const MIN_SEGMENT_AREA_M2 = 8.0;

    /** @param array<string, mixed> $panelDefaults */
    public function build(BuildingInsights $insights, array $panelDefaults): RoofLayout
    {
        $panelW  = (float)($panelDefaults['width_m']   ?? 1.134);
        $panelH  = (float)($panelDefaults['height_m']  ?? 1.722);
        $wattPk  = (int)  ($panelDefaults['watt_peak'] ?? 420);
        $setback = (float)($panelDefaults['setback_m'] ?? 0.40);
        $rowGap  = (float)($panelDefaults['row_gap_m'] ?? 0.02);
        $colGap  = (float)($panelDefaults['col_gap_m'] ?? 0.02);

        $segmentLayouts = [];
        $globalPanelIndex = 0;

        foreach ($insights->segments as $segment) {
            if ($segment->areaM2 < self::MIN_SEGMENT_AREA_M2) {
                continue;
            }

            $candidates = [
                $this->fit($segment, 'portrait', $panelW, $panelH, $setback, $rowGap, $colGap, $wattPk),
                $this->fit($segment, 'landscape', $panelH, $panelW, $setback, $rowGap, $colGap, $wattPk),
            ];

            // Prefer the layout with more panels; tiebreak on annual energy.
            usort($candidates, function ($a, $b) {
                if ($a['panels'] !== $b['panels']) {
                    return $b['panels'] <=> $a['panels'];
                }
                return $b['annual_kwh'] <=> $a['annual_kwh'];
            });

            $chosen = $candidates[0];
            if ($chosen['panels'] === 0) {
                continue;
            }

            $placements = [];
            foreach ($chosen['positions'] as $pos) {
                $globalPanelIndex++;
                $placements[] = new PanelPlacement(
                    index: $globalPanelIndex,
                    segmentIndex: $segment->index,
                    x: $pos['x'],
                    y: $pos['y'],
                    width: $pos['w'],
                    height: $pos['h'],
                    orientation: $chosen['orientation'],
                );
            }

            $segmentLayouts[] = new SegmentLayout(
                segmentIndex: $segment->index,
                orientation: $chosen['orientation'],
                rows: $chosen['rows'],
                cols: $chosen['cols'],
                panels: $placements,
                annualKwh: round($chosen['annual_kwh'], 1),
                usedAreaM2: round($chosen['panels'] * $panelW * $panelH, 2),
            );
        }

        $totalPanels = 0;
        $totalKwh = 0.0;
        $aesthetic = 0.0;
        foreach ($segmentLayouts as $s) {
            $totalPanels += $s->panelCount();
            $totalKwh    += $s->annualKwh;
            if ($s->rows > 0 && $s->cols > 0) {
                $aesthetic += $s->rows * $s->cols === $s->panelCount() ? 1.0 : 0.5;
            }
        }
        $aestheticScore = count($segmentLayouts) > 0
            ? round($aesthetic / count($segmentLayouts), 2)
            : 0.0;

        return new RoofLayout(
            segments: $segmentLayouts,
            panelWidthM: $panelW,
            panelHeightM: $panelH,
            panelWattPeak: $wattPk,
            setbackM: $setback,
            totalPanels: $totalPanels,
            totalKwp: round(($totalPanels * $wattPk) / 1000.0, 2),
            annualKwh: round($totalKwh, 1),
            aestheticScore: $aestheticScore,
        );
    }

    /**
     * Fit a rectangular panel grid into a segment rectangle.
     *
     * Width/height below refer to the already-oriented panel (portrait passes
     * panelW/panelH unchanged; landscape swaps them).
     *
     * @return array{
     *     orientation: string,
     *     rows: int,
     *     cols: int,
     *     panels: int,
     *     annual_kwh: float,
     *     positions: list<array{x: float, y: float, w: float, h: float}>
     * }
     */
    private function fit(
        RoofSegment $segment,
        string $orientation,
        float $panelWidth,
        float $panelHeight,
        float $setback,
        float $rowGap,
        float $colGap,
        int $wattPeak,
    ): array {
        $usableW = max(0.0, $segment->widthM  - 2.0 * $setback);
        $usableH = max(0.0, $segment->heightM - 2.0 * $setback);

        if ($usableW < $panelWidth || $usableH < $panelHeight) {
            return $this->emptyFit($orientation);
        }

        $cols = (int) floor(($usableW + $colGap) / ($panelWidth + $colGap));
        $rows = (int) floor(($usableH + $rowGap) / ($panelHeight + $rowGap));
        $panels = max(0, $cols * $rows);

        $positions = [];
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $positions[] = [
                    'x' => round($setback + $c * ($panelWidth + $colGap), 3),
                    'y' => round($setback + $r * ($panelHeight + $rowGap), 3),
                    'w' => $panelWidth,
                    'h' => $panelHeight,
                ];
            }
        }

        // Cap by Solar API's declared solarPotentialMaxPanels if it is smaller
        // than the raw grid count — respects the provider's own ceiling.
        $panels = min($panels, count($positions));

        $kwhPerKwp = $segment->sunshineHoursPerYear * self::PERFORMANCE_RATIO
            * (1.0 - $segment->shadePenalty)
            * $this->pitchEfficiency($segment->pitchDegrees);

        $annualKwh = $panels * ($wattPeak / 1000.0) * $kwhPerKwp;

        return [
            'orientation' => $orientation,
            'rows' => $rows,
            'cols' => $cols,
            'panels' => $panels,
            'annual_kwh' => $annualKwh,
            'positions' => $positions,
        ];
    }

    /** @return array{orientation: string, rows: int, cols: int, panels: int, annual_kwh: float, positions: list<array<string, float>>} */
    private function emptyFit(string $orientation): array
    {
        return [
            'orientation' => $orientation,
            'rows' => 0, 'cols' => 0, 'panels' => 0,
            'annual_kwh' => 0.0, 'positions' => [],
        ];
    }

    /**
     * Very rough optimum-pitch correction: 30° is ideal. Penalise as pitch
     * moves away, but floor the result so steep or flat roofs still produce.
     */
    private function pitchEfficiency(float $pitchDeg): float
    {
        $delta = abs($pitchDeg - 30.0);
        return max(0.78, 1.0 - $delta * 0.004);
    }
}
