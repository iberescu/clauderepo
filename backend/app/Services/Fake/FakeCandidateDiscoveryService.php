<?php

declare(strict_types=1);

namespace App\Services\Fake;

use App\DTOs\Candidate;
use App\DTOs\NormalizedQuery;
use App\Repositories\ProjectFileRepository;
use App\Services\Contracts\CandidateDiscoveryServiceInterface;
use App\Services\StatusFileService;

/**
 * Produces a deterministic list of candidate buildings along the resolved
 * street centre, offset in a straight line using a hash-derived bearing so the
 * same street always yields the same candidates.
 */
final class FakeCandidateDiscoveryService implements CandidateDiscoveryServiceInterface
{
    private const EARTH_RADIUS_M = 6_371_000.0;

    public function __construct(
        private readonly ProjectFileRepository $projects,
        private readonly StatusFileService $status,
    ) {
    }

    public function discover(string $projectId, NormalizedQuery $query): array
    {
        $this->status->progress($projectId, 'candidates.discover start (fake)');

        $count   = (int) config('solar.candidates.max_count', 12);
        $spacing = (float) config('solar.candidates.spacing_meters', 18.0);
        $minConf = (float) config('solar.candidates.min_confidence', 0.35);

        $bearing = $this->bearingFor($query);
        $candidates = [];
        $raw = ["# fake candidate discovery", "bearing_deg: ".round($bearing, 2), ""];

        for ($i = 0; $i < $count; $i++) {
            $distance = ($i - ($count - 1) / 2) * $spacing;
            [$lat, $lng] = $this->offset($query->centerLat, $query->centerLng, $bearing, $distance);

            $houseNumber = 2 + $i * 2;
            $confidence = max($minConf, round(0.95 - $i * 0.02, 2));
            $address = sprintf('%d %s, %s', $houseNumber, $query->street, $query->city);
            $solarSupported = $i % 7 !== 6;
            $notes = $solarSupported ? '' : 'solar_unsupported=preliminary';

            $candidates[] = new Candidate(
                id: sprintf('%s-%03d', substr(sha1($query->formattedAddress.$i), 0, 10), $i + 1),
                index: $i + 1,
                formattedAddress: $address,
                lat: round($lat, 6),
                lng: round($lng, 6),
                confidence: $confidence,
                solarSupported: $solarSupported,
                notes: $notes,
            );

            $raw[] = sprintf('%02d\t%s\t%.6f,%.6f\tconf=%.2f', $i + 1, $address, $lat, $lng, $confidence);
        }

        $this->projects->writeText($projectId, 'candidates/discovery_raw.txt', implode("\n", $raw)."\n");
        $this->status->apiCall($projectId, 'fake.candidates', 200, 0.0, 'count='.count($candidates));
        $this->status->progress($projectId, 'candidates.discover ok count='.count($candidates));

        return $candidates;
    }

    private function bearingFor(NormalizedQuery $query): float
    {
        $hash = sha1($query->formattedAddress);
        return (hexdec(substr($hash, 16, 8)) % 360);
    }

    /** @return array{0: float, 1: float} */
    private function offset(float $lat, float $lng, float $bearingDeg, float $distanceM): array
    {
        $bearing = deg2rad($bearingDeg);
        $latRad  = deg2rad($lat);
        $lngRad  = deg2rad($lng);
        $angular = $distanceM / self::EARTH_RADIUS_M;

        $newLat = asin(sin($latRad) * cos($angular) + cos($latRad) * sin($angular) * cos($bearing));
        $newLng = $lngRad + atan2(
            sin($bearing) * sin($angular) * cos($latRad),
            cos($angular) - sin($latRad) * sin($newLat),
        );

        return [rad2deg($newLat), rad2deg($newLng)];
    }
}
