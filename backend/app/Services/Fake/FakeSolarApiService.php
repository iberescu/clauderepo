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

    public function downloadImagery(string $projectId, Candidate $candidate): array
    {
        $seed = hexdec(substr(sha1($candidate->id.'|imagery'), 0, 8));

        $rgb  = $this->renderFakeAerial($seed);
        $mask = $this->renderFakeMask($seed);
        $flux = $this->renderFakeFlux($seed);

        $this->projects->writeBinary($projectId, 'solar/images/rgb.png',  $rgb);
        $this->projects->writeBinary($projectId, 'solar/images/mask.png', $mask);
        $this->projects->writeBinary($projectId, 'solar/images/flux.png', $flux);
        $this->status->apiCall($projectId, 'fake.dataLayers.imagery', 200, 0.0, 'generated=3');

        return [
            'rgb'  => 'solar/images/rgb.png',
            'mask' => 'solar/images/mask.png',
            'flux' => 'solar/images/flux.png',
        ];
    }

    // Deterministic fake aerial — textured ground + a single darker roof
    // rectangle placed off-centre, so the preview looks like an aerial shot
    // without pretending to be one.
    private function renderFakeAerial(int $seed): string
    {
        $size = 384;
        $img = imagecreatetruecolor($size, $size);
        $rand = fn (int $mod, int $bias = 0) => $bias + ((($seed >> 3) ^ $mod) % 32);

        $groundA = imagecolorallocate($img, 125 + $rand(1), 138 + $rand(2), 110 + $rand(3));
        $groundB = imagecolorallocate($img, 98 + $rand(4),  112 + $rand(5), 84 + $rand(6));
        imagefilledrectangle($img, 0, 0, $size, $size, $groundA);
        for ($y = 0; $y < $size; $y += 4) {
            for ($x = 0; $x < $size; $x += 4) {
                if ((($x * 73 + $y * 131 + $seed) % 11) < 4) {
                    imagefilledrectangle($img, $x, $y, $x + 3, $y + 3, $groundB);
                }
            }
        }

        $roofFill = imagecolorallocate($img, 170, 130, 100);
        $roofLine = imagecolorallocate($img, 80, 60, 40);
        $rx = (int) ($size * 0.28);
        $ry = (int) ($size * 0.30);
        $rw = (int) ($size * 0.46);
        $rh = (int) ($size * 0.42);
        imagefilledrectangle($img, $rx, $ry, $rx + $rw, $ry + $rh, $roofFill);
        imagerectangle($img, $rx, $ry, $rx + $rw, $ry + $rh, $roofLine);
        imageline($img, $rx, $ry + (int) ($rh / 2), $rx + $rw, $ry + (int) ($rh / 2), $roofLine);

        $label = imagecolorallocate($img, 230, 235, 240);
        imagestring($img, 4, 10, $size - 24, 'Aerial (fake)', $label);

        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);
        return $bytes;
    }

    private function renderFakeMask(int $seed): string
    {
        $size = 384;
        $img = imagecreatetruecolor($size, $size);
        $bg = imagecolorallocate($img, 10, 20, 40);
        imagefilledrectangle($img, 0, 0, $size, $size, $bg);
        $tint = imagecolorallocatealpha($img, 37, 99, 235, 40); // #2563EB @ ~70% opacity
        $rx = (int) ($size * 0.26 + ($seed % 11));
        $ry = (int) ($size * 0.28 + ($seed % 7));
        $rw = (int) ($size * 0.48);
        $rh = (int) ($size * 0.42);
        imagealphablending($img, true);
        imagefilledrectangle($img, $rx, $ry, $rx + $rw, $ry + $rh, $tint);
        $label = imagecolorallocate($img, 230, 235, 240);
        imagestring($img, 4, 10, $size - 24, 'Mask overlay (fake)', $label);

        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);
        return $bytes;
    }

    private function renderFakeFlux(int $seed): string
    {
        $size = 384;
        $img = imagecreatetruecolor($size, $size);
        // Deterministic radial heatmap: hotspot near roof centre, cooler at edges.
        $cx = (int) ($size * 0.52);
        $cy = (int) ($size * 0.48);
        $maxD = sqrt($cx * $cx + $cy * $cy);
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $d = sqrt(($x - $cx) ** 2 + ($y - $cy) ** 2) / $maxD; // 0..1
                $t = max(0.0, min(1.0, 1.0 - $d + (($seed + $x * 7 + $y * 13) % 11) / 110.0));
                [$r, $g, $b] = $this->flux3Stop($t);
                imagesetpixel($img, $x, $y, imagecolorallocate($img, $r, $g, $b));
            }
        }
        $label = imagecolorallocate($img, 240, 240, 245);
        imagestring($img, 4, 10, $size - 24, 'Annual flux (fake)', $label);

        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);
        return $bytes;
    }

    /** 3-stop palette: deep blue (#0b3d91) → yellow (#f9d423) → deep red (#b30000). */
    private function flux3Stop(float $t): array
    {
        $stops = [
            [0.0, [0x0b, 0x3d, 0x91]],
            [0.5, [0xf9, 0xd4, 0x23]],
            [1.0, [0xb3, 0x00, 0x00]],
        ];
        for ($i = 1; $i < count($stops); $i++) {
            if ($t <= $stops[$i][0]) {
                $lo = $stops[$i - 1];
                $hi = $stops[$i];
                $k = ($t - $lo[0]) / max(1e-6, ($hi[0] - $lo[0]));
                return [
                    (int) round($lo[1][0] + ($hi[1][0] - $lo[1][0]) * $k),
                    (int) round($lo[1][1] + ($hi[1][1] - $lo[1][1]) * $k),
                    (int) round($lo[1][2] + ($hi[1][2] - $lo[1][2]) * $k),
                ];
            }
        }
        return $stops[count($stops) - 1][1];
    }
}
