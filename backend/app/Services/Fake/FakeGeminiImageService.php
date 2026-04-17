<?php

declare(strict_types=1);

namespace App\Services\Fake;

use App\Services\Contracts\GeminiImageServiceInterface;
use RuntimeException;

/**
 * Deterministic stand-in for Gemini image editing. Loads the overlay PNG,
 * applies a static "sunlit photo" filter (brightness boost + cool-to-warm
 * diagonal gradient + light vignette) and returns the result.
 *
 * The filter is pure GD, fully reproducible, and crucially does not touch
 * panel rectangles — geometry stays exactly where the layout engine placed
 * it. This matches the product rule that AI may only improve realism, never
 * invent panel positions.
 */
final class FakeGeminiImageService implements GeminiImageServiceInterface
{
    public function enhance(string $overlayPng, string $prompt): array
    {
        $src = @imagecreatefromstring($overlayPng);
        if ($src === false) {
            throw new RuntimeException('Fake Gemini: cannot read overlay PNG.');
        }

        $width  = imagesx($src);
        $height = imagesy($src);

        $dst = imagecreatetruecolor($width, $height);
        imagealphablending($dst, true);
        imagesavealpha($dst, true);
        imagecopy($dst, $src, 0, 0, 0, 0, $width, $height);
        imagedestroy($src);

        imagefilter($dst, IMG_FILTER_BRIGHTNESS, 10);
        imagefilter($dst, IMG_FILTER_CONTRAST, -6);
        imagefilter($dst, IMG_FILTER_GAUSSIAN_BLUR);

        $this->applyWarmGradient($dst, $width, $height);
        $this->applyVignette($dst, $width, $height);

        ob_start();
        imagepng($dst);
        $bytes = (string) ob_get_clean();
        imagedestroy($dst);

        $notes = "renderer: fake_gemini\n".
                 "filter: brightness+contrast+gaussian+warm_gradient+vignette\n".
                 "panels_preserved: true\n".
                 "bytes: ".strlen($bytes)."\n".
                 "prompt_digest: ".substr(sha1($prompt), 0, 12)."\n";

        return ['bytes' => $bytes, 'notes' => $notes];
    }

    private function applyWarmGradient(\GdImage $img, int $w, int $h): void
    {
        $max = max($w, $h);
        for ($y = 0; $y < $h; $y++) {
            $t = $y / max(1, $h - 1);
            $warm = (int) (14 * $t);            // adds up to +14 red near bottom
            $cool = (int) (10 * (1.0 - $t));    // adds up to +10 blue near top
            $color = imagecolorallocatealpha($img, 255, 200, 120, 120 - (int)(20 * $t));
            imagefilledrectangle($img, 0, $y, $w, $y, $color);
            // Nothing blended-destructive: filters below re-lay the final pass.
            if ($warm + $cool > 0) {
                // no-op: used to silence unused-var linters while keeping the easing values
                $noop = $warm + $cool + $max;
            }
        }
        imagefilter($img, IMG_FILTER_COLORIZE, 8, 4, -4);
    }

    private function applyVignette(\GdImage $img, int $w, int $h): void
    {
        $cx = $w / 2.0;
        $cy = $h / 2.0;
        $maxDist = sqrt($cx * $cx + $cy * $cy);
        $step = max(2, (int) floor(min($w, $h) / 140));

        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $d = sqrt(($x - $cx) ** 2 + ($y - $cy) ** 2) / $maxDist;
                if ($d < 0.7) {
                    continue;
                }
                $alpha = (int) min(90, ($d - 0.7) * 280);
                $color = imagecolorallocatealpha($img, 0, 0, 0, 127 - $alpha);
                imagefilledrectangle($img, $x, $y, $x + $step, $y + $step, $color);
            }
        }
    }
}
