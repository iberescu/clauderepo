<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\BuildingInsights;
use App\DTOs\Candidate;
use App\DTOs\RoofLayout;
use App\DTOs\RoofSegment;
use App\DTOs\SolarAnalysis;
use App\Repositories\ProjectFileRepository;
use App\Services\Contracts\SolarApiServiceInterface;
use App\Support\TextKv;
use RuntimeException;

/**
 * Orchestrates the Solar API lookups, derives a summary analysis, and drives
 * the layout engine. Keeps controllers free of business logic.
 */
final class SolarAnalysisService
{
    public function __construct(
        private readonly ProjectFileRepository $projects,
        private readonly StatusFileService $status,
        private readonly SolarApiServiceInterface $solar,
        private readonly RoofLayoutService $layout,
        private readonly ConfigFileService $config,
    ) {
    }

    public function analyze(string $projectId): SolarAnalysis
    {
        $candidate = $this->projects->readSelectedCandidate($projectId);
        if ($candidate === null) {
            throw new RuntimeException('No candidate selected for project '.$projectId);
        }

        $this->status->setStatus($projectId, StatusFileService::STATUS_ANALYZING);

        $insights = $this->solar->buildingInsights($projectId, $candidate);
        $this->saveBuildingArtifacts($projectId, $candidate, $insights);

        $layers = $this->solar->dataLayersSummary($projectId, $candidate);
        $analysis = $this->summarize($insights, $layers);

        $this->saveAnalysis($projectId, $analysis, $insights);

        // Download the aerial RGB + mask + annual flux rasters as PNGs so the
        // UI and the PDF can show real imagery. Failures are non-fatal — the
        // deterministic overlay still gets produced in Phase 3.
        $this->solar->downloadImagery($projectId, $candidate);

        $this->status->setStatus($projectId, StatusFileService::STATUS_ANALYSIS_READY,
            sprintf('segments=%d usable_area=%.1fm²', $analysis->usableSegments, $analysis->usableRoofAreaM2));

        return $analysis;
    }

    public function generateLayout(string $projectId): RoofLayout
    {
        $insights = $this->projects->readBuildingInsights($projectId);
        if ($insights === null) {
            throw new RuntimeException('Building insights not found; run analyze-building first.');
        }

        $panelDefaults = $this->config->section('panel');
        $layout = $this->layout->build($insights, $panelDefaults);
        $this->saveLayout($projectId, $layout);

        $this->status->setStatus($projectId, StatusFileService::STATUS_LAYOUT_READY,
            sprintf('panels=%d kwp=%.2f annual=%.1fkWh',
                $layout->totalPanels, $layout->totalKwp, $layout->annualKwh));

        return $layout;
    }

    private function saveBuildingArtifacts(string $projectId, Candidate $candidate, BuildingInsights $insights): void
    {
        $this->projects->saveBuildingInsights($projectId, $candidate, $insights);
    }

    private function saveAnalysis(string $projectId, SolarAnalysis $analysis, BuildingInsights $insights): void
    {
        $this->projects->saveSolarAnalysis($projectId, $analysis);

        $shade = [];
        foreach ($insights->segments as $s) {
            $shade[] = sprintf('segment_%02d_shade_penalty: %.2f', $s->index, $s->shadePenalty);
        }
        $this->projects->writeText($projectId, 'solar/shade_summary.txt', implode("\n", $shade)."\n");
    }

    private function saveLayout(string $projectId, RoofLayout $layout): void
    {
        $this->projects->saveLayout($projectId, $layout);
    }

    /** @param array{annual_flux_max: float, annual_flux_mean: float, mask_coverage: float} $layers */
    private function summarize(BuildingInsights $insights, array $layers): SolarAnalysis
    {
        $usableSegments = array_values(array_filter($insights->segments, fn (RoofSegment $s) => $s->areaM2 >= 8.0));
        $count = count($usableSegments);
        $area  = array_sum(array_map(fn (RoofSegment $s) => $s->areaM2, $usableSegments));
        $pitch = $count > 0 ? array_sum(array_map(fn (RoofSegment $s) => $s->pitchDegrees, $usableSegments)) / $count : 0.0;
        $sun   = $count > 0 ? array_sum(array_map(fn (RoofSegment $s) => $s->sunshineHoursPerYear, $usableSegments)) / $count : 0.0;
        $shade = $count > 0 ? array_sum(array_map(fn (RoofSegment $s) => $s->shadePenalty, $usableSegments)) / $count : 0.0;

        // Dominant azimuth: pick the segment with the largest area.
        $dominant = 0.0;
        $bestArea = 0.0;
        foreach ($usableSegments as $s) {
            if ($s->areaM2 > $bestArea) {
                $bestArea = $s->areaM2;
                $dominant = $s->azimuthDegrees;
            }
        }

        $confidence = match ($insights->imageryQuality) {
            'HIGH'   => 0.9,
            'MEDIUM' => 0.75,
            default  => 0.6,
        };

        if ($layers['mask_coverage'] < 0.5) {
            $confidence *= 0.85;
        }

        return new SolarAnalysis(
            usableRoofAreaM2: round($area, 2),
            averagePitchDeg: round($pitch, 1),
            dominantAzimuthDeg: round($dominant, 1),
            averageSunshineHoursPerYear: round($sun, 1),
            shadingScore: round(1.0 - $shade, 2),
            confidence: round($confidence, 2),
            imageryQuality: $insights->imageryQuality,
            imageryDate: $insights->imageryDate,
            usableSegments: $count,
        );
    }
}
