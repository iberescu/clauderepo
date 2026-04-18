<?php

declare(strict_types=1);

namespace App\Services\Contracts;

/**
 * Fetches a top-down satellite tile from Google's Static Maps API (or a
 * deterministic fake). The tile is always returned as PNG bytes together with
 * the geographic metadata required by PanelCompositor to project panel
 * rectangles onto it.
 */
interface StaticMapsServiceInterface
{
    /**
     * Fetch a square satellite tile centred on (lat, lng).
     *
     * @return array{
     *     bytes: string,
     *     width: int,
     *     height: int,
     *     zoom: int,
     *     center_lat: float,
     *     center_lng: float,
     *     meters_per_pixel: float,
     * }
     */
    public function fetchSatelliteTile(
        string $projectId,
        float $lat,
        float $lng,
        int $zoom = 20,
        int $sizePx = 640,
    ): array;
}
