<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\RoofLayout;
use App\DTOs\SegmentLayout;
use App\Repositories\ProjectFileRepository;
use App\Services\Contracts\GeminiImageServiceInterface;
use App\Services\Contracts\StaticMapsServiceInterface;
use App\Support\PanelCompositor;
use GdImage;
use RuntimeException;
use Throwable;

/**
 * Turns the deterministic layout into four PNGs:
 *
 *   render/roof_base.png      — clean top-down roof (no panels)
 *   render/roof_overlay.png   — roof + exact panel rectangles (the mask that
 *                               is handed to Gemini)
 *   render/roof_render.png    — photorealistic top-down version from Gemini
 *   render/roof_render_3d.png — aerial 3D perspective view from Gemini, meant
 *                               to be the hero image in the sales proposal
 *
 * The pixel coordinates for the panel rectangles are computed once here and
 * stamped into both overlay and render notes, so downstream steps can verify
 * geometry was preserved.
 */
final class RenderingService
{
    /** pixels per meter in the top-down preview */
    private const SCALE_PX_PER_M = 60;
    private const PADDING_PX     = 40;
    private const SEGMENT_GAP_PX = 40;
    private const LABEL_HEIGHT_PX = 26;

    public function __construct(
        private readonly ProjectFileRepository $projects,
        private readonly StatusFileService $status,
        private readonly GeminiImageServiceInterface $gemini,
        private readonly StaticMapsServiceInterface $staticMaps,
    ) {
    }

    public function generate(string $projectId): string
    {
        $layout = $this->projects->readFullLayout($projectId);
        if ($layout === null || $layout->totalPanels === 0) {
            throw new RuntimeException('Layout not generated yet or empty; run generate-layout first.');
        }

        $this->status->setStatus($projectId, StatusFileService::STATUS_RENDERING);

        [$basePng, $canvas]    = $this->drawBase($layout);
        $overlayPng            = $this->drawOverlay($basePng, $layout, $canvas);

        $this->projects->writeBinary($projectId, 'render/roof_base.png', $basePng);
        $this->projects->writeBinary($projectId, 'render/roof_overlay.png', $overlayPng);

        $prompt = $this->prompt();
        $this->projects->writeText($projectId, 'render/gemini_prompt.txt', $prompt);

        $renderNotes = "renderer_input:\n".
            "  canvas_w_px: {$canvas['w']}\n".
            "  canvas_h_px: {$canvas['h']}\n".
            "  scale_px_per_m: ".self::SCALE_PX_PER_M."\n".
            "  panel_count: {$layout->totalPanels}\n".
            "  segments: ".count($layout->segments)."\n";

        try {
            $result = $this->gemini->enhance($overlayPng, $prompt);
            $this->projects->writeBinary($projectId, 'render/roof_render.png', $result['bytes']);
            $renderNotes .= "\nenhanced:\n".$result['notes'];
            $this->status->progress($projectId, 'render.enhance ok bytes='.strlen($result['bytes']));
        } catch (\Throwable $e) {
            $this->status->error($projectId, 'render.enhance failed, falling back to overlay', $e);
            $this->projects->writeBinary($projectId, 'render/roof_render.png', $overlayPng);
            $renderNotes .= "\nenhanced:\n  renderer: fallback_overlay\n  error: ".$e->getMessage()."\n";
        }

        $prompt3d = $this->prompt3d();
        $this->projects->writeText($projectId, 'render/gemini_prompt_3d.txt', $prompt3d);
        try {
            $result3d = $this->gemini->enhance($overlayPng, $prompt3d);
            $this->projects->writeBinary($projectId, 'render/roof_render_3d.png', $result3d['bytes']);
            $renderNotes .= "\nenhanced_3d:\n".$result3d['notes'];
            $this->status->progress($projectId, 'render.enhance_3d ok bytes='.strlen($result3d['bytes']));
        } catch (\Throwable $e) {
            $this->status->error($projectId, 'render.enhance_3d failed, falling back to top-down render', $e);
            $topDown = $this->projects->readBinary($projectId, 'render/roof_render.png') ?? $overlayPng;
            $this->projects->writeBinary($projectId, 'render/roof_render_3d.png', $topDown);
            $renderNotes .= "\nenhanced_3d:\n  renderer: fallback_top_down\n  error: ".$e->getMessage()."\n";
        }

        $renderNotes .= $this->renderRealPhotoComposites($projectId, $layout);

        $this->projects->writeText($projectId, 'render/render_notes.txt', $renderNotes);
        $this->status->setStatus($projectId, StatusFileService::STATUS_RENDER_READY,
            'panels='.$layout->totalPanels);

        return $renderNotes;
    }

    /**
     * Builds two real-photo composites (Solar API aerial + Static Maps
     * satellite) with panel rectangles projected via lat/lng, then feeds
     * each to Gemini for a 3D perspective hero render. Failures are
     * non-fatal — the synthetic top-down remains the guaranteed output.
     */
    private function renderRealPhotoComposites(string $projectId, RoofLayout $layout): string
    {
        $insights = $this->projects->readBuildingInsights($projectId);
        $candidate = $this->projects->readSelectedCandidate($projectId);
        if ($insights === null || $candidate === null) {
            return "\nreal_photo:\n  skipped: no building insights or candidate\n";
        }

        $notes = '';
        $prompt3d = $this->prompt3d();

        // --- 1) Solar API RGB aerial --------------------------------------
        $aerialPng = $this->projects->readBinary($projectId, 'solar/images/rgb.png');
        $aerialGeo = $this->projects->readJson($projectId, 'solar/images/aerial_geo.json');
        if ($aerialPng !== null && is_array($aerialGeo)) {
            try {
                $overlay = PanelCompositor::compose(
                    $aerialPng, $layout, $insights,
                    [
                        'center_lat'       => (float)($aerialGeo['center_lat'] ?? 0),
                        'center_lng'       => (float)($aerialGeo['center_lng'] ?? 0),
                        'width_px'         => (int)($aerialGeo['width_px'] ?? 0),
                        'height_px'        => (int)($aerialGeo['height_px'] ?? 0),
                        'meters_per_pixel' => (float)($aerialGeo['meters_per_pixel'] ?? 0.15),
                    ],
                    'Solar aerial',
                );
                $this->projects->writeBinary($projectId, 'render/real_aerial_overlay.png', $overlay);
                $notes .= $this->enhance3d($projectId, 'real_aerial', $overlay, $prompt3d);
            } catch (Throwable $e) {
                $this->status->error($projectId, 'render.real_aerial_compose failed', $e);
                $notes .= "\nreal_aerial:\n  compose_error: ".$e->getMessage()."\n";
            }
        } else {
            $notes .= "\nreal_aerial:\n  skipped: solar/images/rgb.png or aerial_geo.json missing\n";
        }

        // --- 2) Static Maps satellite tile --------------------------------
        try {
            $tile = $this->staticMaps->fetchSatelliteTile(
                $projectId, $candidate->lat, $candidate->lng, 20, 640,
            );
            $this->projects->writeBinary($projectId, 'render/static_map_base.png', (string) $tile['bytes']);
            $this->projects->writeJson($projectId, 'render/static_map_geo.json', [
                'source'           => 'google.staticMaps.satellite',
                'center_lat'       => $tile['center_lat'],
                'center_lng'       => $tile['center_lng'],
                'zoom'             => $tile['zoom'],
                'width_px'         => $tile['width'],
                'height_px'        => $tile['height'],
                'meters_per_pixel' => $tile['meters_per_pixel'],
            ]);

            $overlay = PanelCompositor::compose(
                (string) $tile['bytes'], $layout, $insights,
                [
                    'center_lat'       => (float) $tile['center_lat'],
                    'center_lng'       => (float) $tile['center_lng'],
                    'width_px'         => (int) $tile['width'],
                    'height_px'        => (int) $tile['height'],
                    'meters_per_pixel' => (float) $tile['meters_per_pixel'],
                ],
                'Satellite tile',
            );
            $this->projects->writeBinary($projectId, 'render/static_map_overlay.png', $overlay);
            $notes .= $this->enhance3d($projectId, 'static_map', $overlay, $prompt3d);
        } catch (Throwable $e) {
            $this->status->error($projectId, 'render.static_map failed', $e);
            $notes .= "\nstatic_map:\n  fetch_or_compose_error: ".$e->getMessage()."\n";
        }

        return $notes;
    }

    private function enhance3d(string $projectId, string $label, string $overlayPng, string $prompt): string
    {
        $outFile = 'render/'.$label.'_render_3d.png';
        try {
            $result = $this->gemini->enhance($overlayPng, $prompt);
            $this->projects->writeBinary($projectId, $outFile, $result['bytes']);
            $this->status->progress($projectId, "render.enhance_3d {$label} ok bytes=".strlen($result['bytes']));
            return "\n{$label}_3d:\n".$result['notes'];
        } catch (Throwable $e) {
            $this->status->error($projectId, "render.enhance_3d {$label} failed, falling back to overlay", $e);
            $this->projects->writeBinary($projectId, $outFile, $overlayPng);
            return "\n{$label}_3d:\n  renderer: fallback_overlay\n  error: ".$e->getMessage()."\n";
        }
    }

    public function prompt(): string
    {
        return <<<PROMPT
Create a photorealistic rooftop solar installation mockup.
Preserve the exact panel positions defined by the supplied overlay mask.
Do not add or remove panels.
Do not change roof geometry.
Improve realism, lighting, reflections, and shadows only.
Use a soft morning light and a shallow isometric top-down perspective.
Keep dark blue monocrystalline panels with silver frames.
PROMPT;
    }

    public function prompt3d(): string
    {
        return <<<PROMPT
Render an aerial 3D perspective of the same rooftop seen from roughly 45
degrees, as if taken from a low drone shot. Keep the exact panel grid
positions from the overlay mask — do not invent additional panels or move
existing ones. Preserve roof geometry and proportions.
Use dark blue monocrystalline panels with silver frames.
Use warm late-afternoon sunlight coming from the upper left, with soft
shadows cast by the panels onto the roof surface, subtle sky reflections on
the glass, and a clean suburban neighbourhood blurred gently in the
background. The result should feel like a realistic sales marketing photo
while still being clearly recognisable as the same rooftop in the supplied
top-down mask.
PROMPT;
    }

    /**
     * @return array{0: string, 1: array{w: int, h: int, segments: list<array{x: int, y: int, w: int, h: int}>}}
     */
    private function drawBase(RoofLayout $layout): array
    {
        $segmentsCanvas = [];
        $widestPx = 0;
        $cursorY  = self::PADDING_PX;

        foreach ($layout->segments as $i => $s) {
            $sw = (int) ceil($this->segmentWidthM($s, $layout) * self::SCALE_PX_PER_M);
            $sh = (int) ceil($this->segmentHeightM($s, $layout) * self::SCALE_PX_PER_M);
            if ($sw <= 0 || $sh <= 0) {
                continue;
            }

            $segmentsCanvas[] = [
                'x' => self::PADDING_PX,
                'y' => $cursorY + self::LABEL_HEIGHT_PX,
                'w' => $sw,
                'h' => $sh,
                'segment' => $s,
            ];
            $widestPx = max($widestPx, $sw);
            $cursorY += self::LABEL_HEIGHT_PX + $sh + self::SEGMENT_GAP_PX;
        }

        $canvasW = $widestPx + self::PADDING_PX * 2;
        $canvasH = max($cursorY + self::PADDING_PX, 200);

        $img = imagecreatetruecolor($canvasW, $canvasH);
        imagealphablending($img, true);
        imagesavealpha($img, true);

        $bg    = imagecolorallocate($img, 244, 246, 248);
        $grid  = imagecolorallocate($img, 226, 231, 236);
        $tile  = imagecolorallocate($img, 189, 176, 162);
        $border= imagecolorallocate($img, 96, 82, 70);
        $ink   = imagecolorallocate($img, 55, 65, 80);

        imagefilledrectangle($img, 0, 0, $canvasW, $canvasH, $bg);
        for ($x = 0; $x < $canvasW; $x += self::SCALE_PX_PER_M) {
            imageline($img, $x, 0, $x, $canvasH, $grid);
        }
        for ($y = 0; $y < $canvasH; $y += self::SCALE_PX_PER_M) {
            imageline($img, 0, $y, $canvasW, $y, $grid);
        }

        $simpleOut = [];
        foreach ($segmentsCanvas as $sc) {
            /** @var SegmentLayout $s */
            $s = $sc['segment'];
            imagefilledrectangle($img, $sc['x'], $sc['y'], $sc['x'] + $sc['w'], $sc['y'] + $sc['h'], $tile);
            imagerectangle($img, $sc['x'], $sc['y'], $sc['x'] + $sc['w'], $sc['y'] + $sc['h'], $border);
            imagestring($img, 4, $sc['x'], max(0, $sc['y'] - self::LABEL_HEIGHT_PX + 6),
                sprintf('Segment %d  -  orientation:%s  rows:%d cols:%d  panels:%d',
                    $s->segmentIndex, $s->orientation, $s->rows, $s->cols, $s->panelCount()),
                $ink);
            $simpleOut[] = ['x' => $sc['x'], 'y' => $sc['y'], 'w' => $sc['w'], 'h' => $sc['h']];
        }

        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return [$bytes, ['w' => $canvasW, 'h' => $canvasH, 'segments' => $simpleOut]];
    }

    /** @param array{w: int, h: int, segments: list<array{x: int, y: int, w: int, h: int}>} $canvas */
    private function drawOverlay(string $basePng, RoofLayout $layout, array $canvas): string
    {
        $img = @imagecreatefromstring($basePng);
        if ($img === false) {
            throw new RuntimeException('Overlay: cannot decode base PNG.');
        }
        imagealphablending($img, true);
        imagesavealpha($img, true);

        $panelFill   = imagecolorallocate($img, 28, 46, 98);
        $panelFrame  = imagecolorallocate($img, 210, 220, 235);
        $panelShine  = imagecolorallocatealpha($img, 255, 255, 255, 96);

        $segIndexToCanvas = [];
        foreach ($layout->segments as $idx => $seg) {
            $segIndexToCanvas[$seg->segmentIndex] = $canvas['segments'][$idx] ?? null;
        }

        foreach ($layout->allPanels() as $p) {
            $rect = $segIndexToCanvas[$p->segmentIndex] ?? null;
            if ($rect === null) {
                continue;
            }
            $px = $rect['x'] + (int) round($p->x * self::SCALE_PX_PER_M);
            $py = $rect['y'] + (int) round($p->y * self::SCALE_PX_PER_M);
            $pw = (int) round($p->width * self::SCALE_PX_PER_M);
            $ph = (int) round($p->height * self::SCALE_PX_PER_M);

            imagefilledrectangle($img, $px, $py, $px + $pw, $py + $ph, $panelFill);
            imagerectangle($img, $px, $py, $px + $pw, $py + $ph, $panelFrame);
            imagefilledrectangle($img, $px + 2, $py + 2, $px + (int) max(3, $pw * 0.25), $py + (int) max(3, $ph * 0.08), $panelShine);
        }

        $this->drawLegend($img, $layout, $canvas['w']);

        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);
        return $bytes;
    }

    private function drawLegend(GdImage $img, RoofLayout $layout, int $canvasW): void
    {
        $ink = imagecolorallocate($img, 40, 50, 60);
        imagestring($img, 5, 12, 8,
            sprintf('Panels: %d  |  System: %.2f kWp  |  Annual: %d kWh',
                $layout->totalPanels, $layout->totalKwp, (int) round($layout->annualKwh)),
            $ink);
        imagestring($img, 3, max(10, $canvasW - 220), 8,
            sprintf('Panel: %.2fm x %.2fm @ %dWp', $layout->panelWidthM, $layout->panelHeightM, $layout->panelWattPeak),
            $ink);
    }

    private function segmentWidthM(SegmentLayout $s, RoofLayout $l): float
    {
        $panel = $s->orientation === 'portrait' ? $l->panelWidthM : $l->panelHeightM;
        $gap   = 0.02;
        return max(
            2.0,
            2.0 * $l->setbackM + $s->cols * $panel + max(0, $s->cols - 1) * $gap,
        );
    }

    private function segmentHeightM(SegmentLayout $s, RoofLayout $l): float
    {
        $panel = $s->orientation === 'portrait' ? $l->panelHeightM : $l->panelWidthM;
        $gap   = 0.02;
        return max(
            2.0,
            2.0 * $l->setbackM + $s->rows * $panel + max(0, $s->rows - 1) * $gap,
        );
    }
}
