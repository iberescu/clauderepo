<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\DTOs\NormalizedQuery;
use App\DTOs\ProjectInput;
use App\Repositories\ProjectFileRepository;
use App\Services\Contracts\GeoSearchServiceInterface;
use App\Services\StatusFileService;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

/**
 * Real implementation backed by the Google Geocoding API. Raw payloads are
 * saved to input/geocode_raw.txt; request timing is written to api_calls.txt.
 */
final class GoogleGeoSearchService implements GeoSearchServiceInterface
{
    public function __construct(
        private readonly ProjectFileRepository $projects,
        private readonly StatusFileService $status,
        private readonly HttpFactory $http,
    ) {
    }

    public function resolve(string $projectId, ProjectInput $input): NormalizedQuery
    {
        $key = (string) config('solar.google.maps_api_key');
        if ($key === '') {
            throw new RuntimeException('GOOGLE_MAPS_API_KEY missing; set FAKE_PROVIDERS=true for offline mode.');
        }

        $endpoint = (string) config('solar.google.geocoding_endpoint');
        $formatted = trim(sprintf('%s, %s, %s', $input->street, $input->city, $input->country), ', ');

        $started = microtime(true);
        $resp = $this->http->timeout(20)->get($endpoint, [
            'address' => $formatted,
            'key'     => $key,
        ]);
        $duration = (microtime(true) - $started) * 1000;

        $body = $resp->body();
        $this->projects->writeText($projectId, 'input/geocode_raw.txt', $body);
        $this->status->apiCall($projectId, 'google.geocode', $resp->status(), $duration);

        if (!$resp->ok()) {
            throw new RuntimeException('Geocoding failed: HTTP '.$resp->status());
        }

        $data = $resp->json();
        $first = $data['results'][0] ?? null;
        if ($first === null) {
            throw new RuntimeException('Geocoding returned no results for "'.$formatted.'"');
        }

        $loc = $first['geometry']['location'] ?? ['lat' => 0, 'lng' => 0];
        $locationType = (string) ($first['geometry']['location_type'] ?? 'APPROXIMATE');

        return new NormalizedQuery(
            street: $input->street,
            city: $input->city,
            country: $input->country,
            formattedAddress: (string) ($first['formatted_address'] ?? $formatted),
            centerLat: (float) $loc['lat'],
            centerLng: (float) $loc['lng'],
            confidence: $this->confidenceFor($locationType),
            placeId: (string) ($first['place_id'] ?? ''),
            provider: 'google',
        );
    }

    private function confidenceFor(string $locationType): float
    {
        return match ($locationType) {
            'ROOFTOP'             => 0.95,
            'RANGE_INTERPOLATED'  => 0.80,
            'GEOMETRIC_CENTER'    => 0.65,
            default               => 0.45,
        };
    }
}
