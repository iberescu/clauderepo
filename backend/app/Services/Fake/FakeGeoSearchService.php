<?php

declare(strict_types=1);

namespace App\Services\Fake;

use App\DTOs\NormalizedQuery;
use App\DTOs\ProjectInput;
use App\Repositories\ProjectFileRepository;
use App\Services\Contracts\GeoSearchServiceInterface;
use App\Services\StatusFileService;

/**
 * Deterministic geocoding double. The centre latitude/longitude are derived
 * from a hash of the street/city/country so the same query always resolves to
 * the same point, without making any network calls.
 */
final class FakeGeoSearchService implements GeoSearchServiceInterface
{
    public function __construct(
        private readonly ProjectFileRepository $projects,
        private readonly StatusFileService $status,
    ) {
    }

    public function resolve(string $projectId, ProjectInput $input): NormalizedQuery
    {
        $this->status->progress($projectId, 'geo.resolve start (fake)');

        if ($input->hasCoordinates()) {
            [$lat, $lng] = [(float) $input->lat, (float) $input->lng];
        } else {
            [$lat, $lng] = $this->pointFor($input);
        }

        $formatted = $input->street !== '' || $input->city !== '' || $input->country !== ''
            ? trim(sprintf('%s, %s, %s', $input->street, $input->city, $input->country), ', ')
            : sprintf('%.6f, %.6f', $lat, $lng);
        $query = new NormalizedQuery(
            street: $input->street,
            city: $input->city,
            country: $input->country,
            formattedAddress: $formatted,
            centerLat: $lat,
            centerLng: $lng,
            confidence: $input->hasCoordinates() ? 1.0 : 0.90,
            placeId: 'fake_'.substr(sha1($formatted), 0, 16),
            provider: 'fake',
        );

        $this->projects->writeText($projectId, 'input/geocode_raw.txt',
            "# fake geocode response\n".
            "formatted_address: {$formatted}\n".
            "lat: {$lat}\nlng: {$lng}\nconfidence: 0.90\n"
        );
        $this->status->apiCall($projectId, 'fake.geocode', 200, 0.0, "formatted={$formatted}");
        $this->status->progress($projectId, 'geo.resolve ok (fake) formatted='.$formatted);

        return $query;
    }

    /** @return array{0: float, 1: float} */
    private function pointFor(ProjectInput $input): array
    {
        $hash = sha1(strtolower($input->street.'|'.$input->city.'|'.$input->country));
        $latRaw = hexdec(substr($hash, 0, 8));
        $lngRaw = hexdec(substr($hash, 8, 8));

        $lat = 35.0 + ($latRaw % 250000) / 250000.0 * 25.0;
        $lng = -10.0 + ($lngRaw % 400000) / 400000.0 * 40.0;

        return [round($lat, 6), round($lng, 6)];
    }
}
