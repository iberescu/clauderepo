<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\DTOs\BuildingInsights;
use App\DTOs\Candidate;
use App\DTOs\RoofSegment;
use App\Repositories\ProjectFileRepository;
use App\Services\Contracts\SolarApiServiceInterface;
use App\Services\StatusFileService;
use App\Support\TiffPreview;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;
use Throwable;

/**
 * Real Google Solar API client. Implements Building Insights + a lightweight
 * Data Layers summary; raw payloads are saved alongside the text summaries.
 */
final class GoogleSolarApiService implements SolarApiServiceInterface
{
    public function __construct(
        private readonly ProjectFileRepository $projects,
        private readonly StatusFileService $status,
        private readonly HttpFactory $http,
    ) {
    }

    public function buildingInsights(string $projectId, Candidate $candidate): BuildingInsights
    {
        $key = (string) config('solar.google.solar_api_key');
        if ($key === '') {
            throw new RuntimeException('GOOGLE_SOLAR_API_KEY missing; set FAKE_PROVIDERS=true for offline mode.');
        }
        $endpoint = rtrim((string) config('solar.google.solar_endpoint'), '/').'/buildingInsights:findClosest';

        $started = microtime(true);
        $resp = $this->http->timeout(30)->get($endpoint, [
            'location.latitude'  => $candidate->lat,
            'location.longitude' => $candidate->lng,
            'requiredQuality'    => 'LOW',
            'key'                => $key,
        ]);
        $duration = (microtime(true) - $started) * 1000;

        $this->projects->writeText($projectId, 'building/building_insights_raw.txt', $resp->body());
        $this->status->apiCall($projectId, 'google.buildingInsights', $resp->status(), $duration);

        if (!$resp->ok()) {
            throw new RuntimeException('Solar Building Insights failed: HTTP '.$resp->status());
        }

        return $this->parseInsights($resp->json(), $candidate);
    }

    public function dataLayersSummary(string $projectId, Candidate $candidate): array
    {
        $key = (string) config('solar.google.solar_api_key');
        if ($key === '') {
            throw new RuntimeException('GOOGLE_SOLAR_API_KEY missing.');
        }
        $endpoint = rtrim((string) config('solar.google.solar_endpoint'), '/').'/dataLayers:get';

        $started = microtime(true);
        $resp = $this->http->timeout(30)->get($endpoint, [
            'location.latitude'  => $candidate->lat,
            'location.longitude' => $candidate->lng,
            'radiusMeters'       => 25,
            'view'               => 'FULL_LAYERS',
            'requiredQuality'    => 'LOW',
            'key'                => $key,
        ]);
        $duration = (microtime(true) - $started) * 1000;

        $this->projects->writeText($projectId, 'solar/data_layers_raw.txt', $resp->body());
        $this->status->apiCall($projectId, 'google.dataLayers', $resp->status(), $duration);

        if (!$resp->ok()) {
            throw new RuntimeException('Solar Data Layers failed: HTTP '.$resp->status());
        }

        $body = $resp->json();
        $summary = [
            'annual_flux_max'  => (float)($body['annualFluxMax'] ?? 0),
            'annual_flux_mean' => (float)($body['annualFluxMean'] ?? 0),
            'mask_coverage'    => (float)($body['imageryProcessedDate']['maskCoverage'] ?? 0.85),
        ];

        $this->projects->writeText($projectId, 'solar/annual_flux_summary.txt',
            sprintf("annual_flux_max_kwh_m2: %.1f\nannual_flux_mean_kwh_m2: %.1f\nmask_coverage: %.3f\n",
                $summary['annual_flux_max'], $summary['annual_flux_mean'], $summary['mask_coverage'])
        );
        return $summary;
    }

    public function downloadImagery(string $projectId, Candidate $candidate): array
    {
        $key = (string) config('solar.google.solar_api_key');
        if ($key === '') {
            return [];
        }
        $raw = $this->projects->readText($projectId, 'solar/data_layers_raw.txt');
        if ($raw === null) {
            return [];
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return [];
        }

        $saved = [];

        $rgbTiff = $this->fetchTiff($projectId, 'rgb', $payload['rgbUrl'] ?? null, $key);
        if ($rgbTiff !== null) {
            $this->projects->writeBinary($projectId, 'solar/images/rgb.tif', $rgbTiff);
            $saved['rgb'] = $this->convertAndStore(
                $projectId, 'rgb.png',
                fn () => TiffPreview::rgbToPng($rgbTiff),
            );
            if ($saved['rgb'] !== null) {
                $this->writeAerialGeo($projectId, $candidate, $saved['rgb']);
            }
        }

        $maskTiff = $this->fetchTiff($projectId, 'mask', $payload['maskUrl'] ?? null, $key);
        if ($maskTiff !== null) {
            $saved['mask'] = $this->convertAndStore(
                $projectId, 'mask.png',
                fn () => TiffPreview::maskOverlayOnRgb($maskTiff, $rgbTiff),
            );
        }

        $fluxTiff = $this->fetchTiff($projectId, 'flux', $payload['annualFluxUrl'] ?? null, $key);
        if ($fluxTiff !== null) {
            $saved['flux'] = $this->convertAndStore(
                $projectId, 'flux.png',
                fn () => TiffPreview::fluxHeatmap($fluxTiff),
            );
        }

        return array_filter($saved, fn ($v) => $v !== null);
    }

    private function fetchTiff(string $projectId, string $label, mixed $url, string $key): ?string
    {
        if (!is_string($url) || $url === '') {
            return null;
        }

        // Google's Data Layers URLs include an already-percent-encoded `id=`
        // query value. Passing a query array to Laravel's HTTP client makes
        // Guzzle re-parse and re-encode the URL, which mangles that id and
        // triggers a 400 from the geoTiff endpoint. Append the API key with
        // string concatenation so the original URL is passed through
        // unchanged.
        $separator = str_contains($url, '?') ? '&' : '?';
        $fullUrl   = $url.$separator.'key='.rawurlencode($key);

        $started = microtime(true);
        try {
            $resp = $this->http->timeout(60)->get($fullUrl);
        } catch (Throwable $e) {
            $this->status->error($projectId, "solar.imagery {$label} fetch failed", $e);
            return null;
        }
        $duration = (microtime(true) - $started) * 1000;

        $note = '';
        if (!$resp->ok()) {
            $snippet = substr((string) $resp->body(), 0, 200);
            $snippet = preg_replace('/\s+/', ' ', $snippet) ?? '';
            $note = 'body='.$snippet;
            $this->status->error($projectId, "solar.imagery {$label} returned HTTP ".$resp->status().': '.$snippet);
        }
        $this->status->apiCall($projectId, "google.dataLayers.{$label}", $resp->status(), $duration, $note);

        if (!$resp->ok()) {
            return null;
        }
        return (string) $resp->body();
    }

    /**
     * @param callable():string $converter
     */
    private function convertAndStore(string $projectId, string $filename, callable $converter): ?string
    {
        try {
            $png = $converter();
        } catch (Throwable $e) {
            $this->status->error($projectId, "solar.imagery {$filename} convert failed", $e);
            return null;
        }
        $this->projects->writeBinary($projectId, 'solar/images/'.$filename, $png);
        $this->status->progress($projectId, 'solar.imagery saved '.$filename.' bytes='.strlen($png));
        return 'solar/images/'.$filename;
    }

    private function writeAerialGeo(string $projectId, Candidate $candidate, string $rgbRelative): void
    {
        $png = $this->projects->readBinary($projectId, $rgbRelative);
        if ($png === null) {
            return;
        }
        $img = @imagecreatefromstring($png);
        if ($img === false) {
            return;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        imagedestroy($img);

        $radiusM = 25.0; // request radius — DataLayers returns a square tile
        $diameter = $radiusM * 2.0;
        $mpp = $diameter / max($w, $h);

        $this->projects->writeJson($projectId, 'solar/images/aerial_geo.json', [
            'source'           => 'google.solar.rgb',
            'center_lat'       => $candidate->lat,
            'center_lng'       => $candidate->lng,
            'radius_meters'    => $radiusM,
            'width_px'         => $w,
            'height_px'        => $h,
            'meters_per_pixel' => $mpp,
        ]);
    }

    /** @param array<string, mixed>|null $body */
    private function parseInsights(?array $body, Candidate $candidate): BuildingInsights
    {
        $body = $body ?? [];
        $center = $body['center'] ?? ['latitude' => $candidate->lat, 'longitude' => $candidate->lng];
        $sp = $body['solarPotential'] ?? [];
        $segmentsRaw = $sp['roofSegmentStats'] ?? [];

        $segments = [];
        foreach ($segmentsRaw as $i => $row) {
            $stats  = $row['stats'] ?? [];
            $sun    = $stats['sunshineQuantiles'] ?? [];
            $bbox   = $row['boundingBox'] ?? [];
            $area   = (float)($stats['areaMeters2'] ?? 0);
            $height = max(1.0, $this->bboxHeightM($bbox));
            $width  = $height > 0 ? round($area / $height, 2) : round(sqrt($area), 2);

            $segments[] = new RoofSegment(
                index: $i + 1,
                pitchDegrees: (float)($row['pitchDegrees'] ?? 20),
                azimuthDegrees: (float)($row['azimuthDegrees'] ?? 180),
                areaM2: round($area, 2),
                widthM: $width,
                heightM: round($height, 2),
                centerLat: (float)(($row['center']['latitude'] ?? $candidate->lat)),
                centerLng: (float)(($row['center']['longitude'] ?? $candidate->lng)),
                sunshineHoursPerYear: (float)($sun[5] ?? 0),
                sunshineP50: (float)($sun[5] ?? 0),
                shadePenalty: max(0.05, 1.0 - ((float)($sun[5] ?? 0) / max(1.0, (float)($sp['maxSunshineHoursPerYear'] ?? 1)))),
            );
        }

        return new BuildingInsights(
            placeId: (string)($body['name'] ?? $candidate->id),
            centerLat: (float)($center['latitude'] ?? $candidate->lat),
            centerLng: (float)($center['longitude'] ?? $candidate->lng),
            imageryQuality: (string)($body['imageryQuality'] ?? 'MEDIUM'),
            imageryDate: (string)($body['imageryDate']['year'] ?? '').(isset($body['imageryDate']['month']) ? '-'.str_pad((string) $body['imageryDate']['month'], 2, '0', STR_PAD_LEFT) : ''),
            wholeRoofAreaM2: (float)($sp['wholeRoofStats']['areaMeters2'] ?? 0),
            groundAreaM2: (float)($sp['buildingAreaMeters2'] ?? 0),
            maxSunshineHoursPerYear: (float)($sp['maxSunshineHoursPerYear'] ?? 0),
            carbonOffsetKgPerMwh: (float)($sp['carbonOffsetFactorKgPerMwh'] ?? 0),
            solarPotentialMaxPanels: (int)($sp['maxArrayPanelsCount'] ?? 0),
            solarPotentialMaxAreaM2: (float)($sp['maxArrayAreaMeters2'] ?? 0),
            segments: $segments,
        );
    }

    /** @param array<string, mixed> $bbox */
    private function bboxHeightM(array $bbox): float
    {
        $sw = $bbox['sw'] ?? null;
        $ne = $bbox['ne'] ?? null;
        if (!$sw || !$ne) {
            return 0.0;
        }
        $latDiff = ((float) $ne['latitude']) - ((float) $sw['latitude']);
        return abs($latDiff) * 111_320.0;
    }
}
