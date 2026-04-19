<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Services\Contracts\StaticMapsServiceInterface;
use App\Services\StatusFileService;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;
use Throwable;

/**
 * Fetches a satellite tile from Google's Static Maps API. At zoom 20 around
 * Lisbon latitude each pixel is ~0.15 m so a 640×640 tile covers roughly a
 * 100 m square — enough to contain a typical detached roof.
 */
final class GoogleStaticMapsService implements StaticMapsServiceInterface
{
    private const ENDPOINT = 'https://maps.googleapis.com/maps/api/staticmap';

    public function __construct(
        private readonly HttpFactory $http,
        private readonly StatusFileService $status,
    ) {
    }

    public function fetchSatelliteTile(
        string $projectId,
        float $lat,
        float $lng,
        int $zoom = 20,
        int $sizePx = 640,
    ): array {
        $key = (string) config('solar.google.maps_api_key');
        if ($key === '') {
            throw new RuntimeException('GOOGLE_MAPS_API_KEY missing; cannot fetch Static Maps tile.');
        }

        $started = microtime(true);
        try {
            $resp = $this->http->timeout(30)->get(self::ENDPOINT, [
                'center'  => "{$lat},{$lng}",
                'zoom'    => $zoom,
                'size'    => "{$sizePx}x{$sizePx}",
                'scale'   => 2,
                'maptype' => 'satellite',
                'format'  => 'png',
                'key'     => $key,
            ]);
        } catch (Throwable $e) {
            $this->status->error($projectId, 'static_maps fetch failed', $e);
            throw new RuntimeException('Static Maps request failed: '.$e->getMessage(), 0, $e);
        }
        $duration = (microtime(true) - $started) * 1000;
        $note = "zoom={$zoom}";
        if (!$resp->ok()) {
            $snippet = substr((string) $resp->body(), 0, 200);
            $snippet = preg_replace('/\s+/', ' ', $snippet) ?? '';
            $note .= ' body='.$snippet;
        }
        $this->status->apiCall($projectId, 'google.staticMaps', $resp->status(), $duration, $note);

        if (!$resp->ok()) {
            $body = substr((string) $resp->body(), 0, 200);
            $body = preg_replace('/\s+/', ' ', $body) ?? '';
            throw new RuntimeException('Static Maps HTTP '.$resp->status().': '.$body);
        }

        $bytes = (string) $resp->body();
        // scale=2 doubles the returned pixel dimensions
        $realPx = $sizePx * 2;

        return [
            'bytes'            => $bytes,
            'width'            => $realPx,
            'height'           => $realPx,
            'zoom'             => $zoom,
            'center_lat'       => $lat,
            'center_lng'       => $lng,
            'meters_per_pixel' => self::metersPerPixel($lat, $zoom) / 2, // /2 because scale=2
        ];
    }

    /** Web Mercator ground resolution at the given latitude + zoom. */
    public static function metersPerPixel(float $lat, int $zoom): float
    {
        return 156543.03392 * cos(deg2rad($lat)) / (2 ** $zoom);
    }
}
