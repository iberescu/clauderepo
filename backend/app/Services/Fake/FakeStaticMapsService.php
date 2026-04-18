<?php

declare(strict_types=1);

namespace App\Services\Fake;

use App\Services\Contracts\StaticMapsServiceInterface;
use App\Services\Google\GoogleStaticMapsService;

/**
 * Deterministic stand-in for Google Static Maps. Renders a tiled "satellite"
 * square via GD — roads, vegetation patches and a central darker roof shape —
 * so offline runs still get a recognisable background to composite on.
 */
final class FakeStaticMapsService implements StaticMapsServiceInterface
{
    public function fetchSatelliteTile(
        string $projectId,
        float $lat,
        float $lng,
        int $zoom = 20,
        int $sizePx = 640,
    ): array {
        $seed = (int) abs(crc32(sprintf('%.6f|%.6f|%d', $lat, $lng, $zoom)));
        $realPx = $sizePx * 2;

        $img = imagecreatetruecolor($realPx, $realPx);
        $rand = fn (int $mod, int $bias = 0) => $bias + ((($seed >> 3) ^ $mod) % 30);

        $ground = imagecolorallocate($img, 118 + $rand(1), 134 + $rand(2), 104 + $rand(3));
        $grove  = imagecolorallocate($img, 68 + $rand(4),  102 + $rand(5), 68 + $rand(6));
        $street = imagecolorallocate($img, 170, 170, 170);
        imagefilledrectangle($img, 0, 0, $realPx, $realPx, $ground);

        // grove patches
        for ($i = 0; $i < 60; $i++) {
            $cx = ($seed * ($i + 3)) % $realPx;
            $cy = ($seed * ($i + 7)) % $realPx;
            $r = 18 + (($seed >> ($i % 5)) % 26);
            imagefilledellipse($img, $cx, $cy, $r, $r, $grove);
        }

        // cross streets
        imagefilledrectangle($img, 0, (int)($realPx * 0.78), $realPx, (int)($realPx * 0.82), $street);
        imagefilledrectangle($img, (int)($realPx * 0.18), 0, (int)($realPx * 0.22), $realPx, $street);

        // central building roof
        $roof = imagecolorallocate($img, 140, 110, 92);
        $roofEdge = imagecolorallocate($img, 70, 50, 36);
        $bx = (int)($realPx * 0.34); $by = (int)($realPx * 0.36);
        $bw = (int)($realPx * 0.32); $bh = (int)($realPx * 0.28);
        imagefilledrectangle($img, $bx, $by, $bx + $bw, $by + $bh, $roof);
        imagerectangle($img, $bx, $by, $bx + $bw, $by + $bh, $roofEdge);
        imageline($img, $bx, $by + (int)($bh / 2), $bx + $bw, $by + (int)($bh / 2), $roofEdge);

        $label = imagecolorallocate($img, 240, 240, 240);
        imagestring($img, 4, 10, $realPx - 24, sprintf('Satellite (fake) z=%d', $zoom), $label);

        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return [
            'bytes'            => $bytes,
            'width'            => $realPx,
            'height'           => $realPx,
            'zoom'             => $zoom,
            'center_lat'       => $lat,
            'center_lng'       => $lng,
            'meters_per_pixel' => GoogleStaticMapsService::metersPerPixel($lat, $zoom) / 2,
        ];
    }
}
