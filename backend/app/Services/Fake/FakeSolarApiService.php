<?php

declare(strict_types=1);

namespace App\Services\Fake;

use App\DTOs\BuildingInsights;
use App\DTOs\Candidate;
use App\DTOs\RoofSegment;
use App\Repositories\ProjectFileRepository;
use App\Services\Contracts\SolarApiServiceInterface;
use App\Services\StatusFileService;

/**
 * Deterministic stand-in for Google's Solar API. Building geometry is derived
 * from a hash of the candidate so the same building always returns the same
 * roof segments, sunshine hours, and shading — no network calls, fully
 * reproducible in tests.
 */
final class FakeSolarApiService implements SolarApiServiceInterface
{
    public function __construct(
        private readonly ProjectFileRepository $projects,
        private readonly StatusFileService $status,
    ) {
    }

    public function buildingInsights(string $projectId, Candidate $candidate): BuildingInsights
    {
        $this->status->progress($projectId, 'solar.building_insights start (fake)');

        $hash = sha1($candidate->id.'|'.$candidate->formattedAddress);
        $rand = fn (int $offset, int $length = 6) => hexdec(substr($hash, $offset, $length));

        $groundArea = 85.0 + ($rand(0) % 140);                     // 85..225 m²
        $segmentCount = 2 + ($rand(6, 2) % 3);                     // 2..4
        $sunshineMax = 1550.0 + ($rand(8) % 650);                  // 1550..2200 h/y
        $imageryQuality = ['HIGH', 'MEDIUM', 'LOW'][$rand(14, 2) % 3];

        $segments = [];
        $used = 0.0;
        for ($i = 0; $i < $segmentCount; $i++) {
            $share = 1.0 / $segmentCount + (($rand(16 + $i * 4, 4) % 200) / 2000.0 - 0.05);
            $share = max(0.18, min(0.55, $share));
            $areaM2 = round(max(12.0, $groundArea * $share * 0.85), 2);
            $used += $areaM2;

            // Rectangular segment with a realistic long:short ratio around 1.3..2.0
            $ratio = 1.3 + (($rand(44 + $i * 4, 4) % 70) / 100.0);
            $heightM = round(sqrt($areaM2 / $ratio), 2);
            $widthM  = round($areaM2 / $heightM, 2);

            $pitch   = 15.0 + ($rand(72 + $i * 4, 4) % 25);         // 15..40°
            $azimuth = ($rand(92 + $i * 4, 4) % 360);                // 0..359°
            $sunP50  = max(800.0, $sunshineMax * (0.62 + (($rand(112 + $i * 4, 4) % 35) / 100.0)));
            $shade   = round(0.05 + (($rand(132 + $i * 4, 4) % 25) / 100.0), 2); // 0.05..0.30

            $segments[] = new RoofSegment(
                index: $i + 1,
                pitchDegrees: (float) $pitch,
                azimuthDegrees: (float) $azimuth,
                areaM2: $areaM2,
                widthM: $widthM,
                heightM: $heightM,
                centerLat: $candidate->lat + (($rand(0, 4) % 20 - 10) / 1_000_000.0) * ($i + 1),
                centerLng: $candidate->lng + (($rand(4, 4) % 20 - 10) / 1_000_000.0) * ($i + 1),
                sunshineHoursPerYear: round($sunP50, 1),
                sunshineP50: round($sunP50, 1),
                shadePenalty: $shade,
            );
        }

        $maxPanels = (int) floor($used / 2.1); // rough upper bound used to mirror Solar API

        $insights = new BuildingInsights(
            placeId: $candidate->id,
            centerLat: $candidate->lat,
            centerLng: $candidate->lng,
            imageryQuality: $imageryQuality,
            imageryDate: '2024-06-01',
            wholeRoofAreaM2: round($used, 2),
            groundAreaM2: round($groundArea, 2),
            maxSunshineHoursPerYear: round($sunshineMax, 1),
            carbonOffsetKgPerMwh: 380.0,
            solarPotentialMaxPanels: $maxPanels,
            solarPotentialMaxAreaM2: round($used, 2),
            segments: $segments,
        );

        $raw = "# fake building insights\n".json_encode($insights->toArray(), JSON_PRETTY_PRINT)."\n";
        $this->projects->writeText($projectId, 'building/building_insights_raw.txt', $raw);
        $this->status->apiCall($projectId, 'fake.buildingInsights', 200, 0.0, 'segments='.$segmentCount);

        return $insights;
    }

    public function dataLayersSummary(string $projectId, Candidate $candidate): array
    {
        $hash = sha1($candidate->id.'|layers');
        $max  = 1600.0 + (hexdec(substr($hash, 0, 6)) % 900);
        $mean = $max * (0.55 + ((hexdec(substr($hash, 6, 6)) % 30) / 100.0));
        $mask = 0.72 + ((hexdec(substr($hash, 12, 6)) % 26) / 100.0);

        $summary = [
            'annual_flux_max'  => round($max, 1),
            'annual_flux_mean' => round($mean, 1),
            'mask_coverage'    => round($mask, 3),
        ];

        $this->projects->writeText($projectId, 'solar/data_layers_raw.txt',
            "# fake data layers summary\n".json_encode($summary, JSON_PRETTY_PRINT)."\n"
        );
        $this->projects->writeText($projectId, 'solar/annual_flux_summary.txt',
            sprintf("annual_flux_max_kwh_m2: %.1f\nannual_flux_mean_kwh_m2: %.1f\nmask_coverage: %.3f\n",
                $summary['annual_flux_max'], $summary['annual_flux_mean'], $summary['mask_coverage'])
        );
        $this->status->apiCall($projectId, 'fake.dataLayers', 200, 0.0, 'mean='.$summary['annual_flux_mean']);

        return $summary;
    }
}
