<?php

declare(strict_types=1);

namespace App\Support;

use App\DTOs\BuildingInsights;
use App\DTOs\RoofLayout;
use App\DTOs\RoofSegment;
use RuntimeException;

/**
 * Projects panel rectangles (meters, segment-local) onto real-photo top-down
 * tiles (Solar API RGB or Google Static Maps satellite). The tile is assumed
 * to be square with known center lat/lng and a uniform meters-per-pixel
 * scale. Panels are converted first to global lat/lng using the parent
 * segment's geographic center, then to pixel offsets from the tile center.
 * Pure-GD drawing — same dark-blue fill + silver frame + highlight used by
 * the synthetic overlay so the two composites look consistent.
 */
final class PanelCompositor
{
    private const LAT_METERS_PER_DEG = 111_320.0;

    /**
     * @param array{
     *   center_lat: float,
     *   center_lng: float,
     *   width_px: int,
     *   height_px: int,
     *   meters_per_pixel: float,
     * } $tileGeo
     */
    public static function compose(
        string $backgroundPng,
        RoofLayout $layout,
        BuildingInsights $insights,
        array $tileGeo,
        string $captionLabel,
    ): string {
        $img = @imagecreatefromstring($backgroundPng);
        if ($img === false) {
            throw new RuntimeException('PanelCompositor: cannot decode background PNG.');
        }
        imagealphablending($img, true);
        imagesavealpha($img, true);

        $w = imagesx($img);
        $h = imagesy($img);
        $mpp = max(1e-6, (float) $tileGeo['meters_per_pixel']);
        $cx = $w / 2.0;
        $cy = $h / 2.0;
        $centerLat = (float) $tileGeo['center_lat'];
        $centerLng = (float) $tileGeo['center_lng'];
        $cosLat = max(1e-6, cos(deg2rad($centerLat)));
        $lngMetersPerDeg = self::LAT_METERS_PER_DEG * $cosLat;

        $segByIdx = [];
        foreach ($insights->segments as $seg) {
            $segByIdx[$seg->index] = $seg;
        }

        $panelFill  = imagecolorallocate($img, 28, 46, 98);
        $panelFrame = imagecolorallocate($img, 210, 220, 235);
        $panelShine = imagecolorallocatealpha($img, 255, 255, 255, 96);

        $drawn = 0;
        foreach ($layout->allPanels() as $p) {
            $seg = $segByIdx[$p->segmentIndex] ?? null;
            if (!$seg instanceof RoofSegment) {
                continue;
            }

            $corners = [];
            foreach ([[0, 0], [$p->width, 0], [$p->width, $p->height], [0, $p->height]] as $corner) {
                [$dx, $dy] = [$p->x + $corner[0], $p->y + $corner[1]];
                // panel local origin = segment SW corner; offset is meters east/north
                $offsetE = $dx - $seg->widthM / 2.0;
                $offsetN = $dy - $seg->heightM / 2.0;

                $panelLat = $seg->centerLat + ($offsetN / self::LAT_METERS_PER_DEG);
                $panelLng = $seg->centerLng + ($offsetE / $lngMetersPerDeg);

                $metersE = ($panelLng - $centerLng) * $lngMetersPerDeg;
                $metersN = ($panelLat - $centerLat) * self::LAT_METERS_PER_DEG;

                $px = (int) round($cx + $metersE / $mpp);
                $py = (int) round($cy - $metersN / $mpp);
                $corners[] = [$px, $py];
            }

            $xs = array_column($corners, 0);
            $ys = array_column($corners, 1);
            $x1 = max(0, min($xs));
            $y1 = max(0, min($ys));
            $x2 = min($w - 1, max($xs));
            $y2 = min($h - 1, max($ys));
            if ($x2 - $x1 < 2 || $y2 - $y1 < 2) {
                continue;
            }

            imagefilledrectangle($img, $x1, $y1, $x2, $y2, $panelFill);
            imagerectangle($img, $x1, $y1, $x2, $y2, $panelFrame);
            $shineW = max(2, (int) round(($x2 - $x1) * 0.25));
            $shineH = max(2, (int) round(($y2 - $y1) * 0.08));
            imagefilledrectangle($img, $x1 + 1, $y1 + 1, $x1 + $shineW, $y1 + $shineH, $panelShine);
            $drawn++;
        }

        $ink = imagecolorallocate($img, 40, 50, 60);
        $bgLabel = imagecolorallocatealpha($img, 255, 255, 255, 32);
        imagefilledrectangle($img, 8, 8, 8 + 360, 30, $bgLabel);
        imagestring($img, 4, 12, 12,
            sprintf('%s - %d panels - %.3f m/px', $captionLabel, $drawn, $mpp),
            $ink,
        );

        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);
        return $bytes;
    }
}
