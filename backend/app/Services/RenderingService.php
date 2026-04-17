<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\PanelPlacement;
use App\DTOs\RoofLayout;
use App\DTOs\SegmentLayout;
use App\Repositories\ProjectFileRepository;
use App\Services\Contracts\GeminiImageServiceInterface;
use GdImage;
use RuntimeException;

/**
 * Turns the deterministic layout into three PNGs:
 *
 *   render/roof_base.png      — clean top-down roof (no panels)
 *   render/roof_overlay.png   — roof + exact panel rectangles (the mask that
 *                               is handed to Gemini)
 *   render/roof_render.png    — photorealistic version returned by the AI
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

        $this->projects->writeText($projectId, 'render/render_notes.txt', $renderNotes);
        $this->status->setStatus($projectId, StatusFileService::STATUS_RENDER_READY,
            'panels='.$layout->totalPanels);

        return $renderNotes;
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
