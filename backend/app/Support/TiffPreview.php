<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Converts the Solar API GeoTIFF rasters (RGB aerial, building mask, annual
 * flux) into browser-friendly PNGs. ImageMagick does the heavy lifting — the
 * backend Dockerfile installs the `imagemagick` package, so `magick` or
 * `convert` is on $PATH in the container. Each conversion writes through a
 * temp file and returns PNG bytes; failures bubble up as RuntimeException so
 * the caller can fall back gracefully.
 */
final class TiffPreview
{
    private const TIMEOUT_SECONDS = 30;

    /** Cache of the resolved ImageMagick binary path. */
    private static ?string $magickBin = null;

    /**
     * Convert a plain RGB GeoTIFF (uint8, 3 bands) to PNG.
     */
    public static function rgbToPng(string $tiffBytes): string
    {
        return self::run($tiffBytes, ['-strip', '-auto-orient']);
    }

    /**
     * Convert a single-band building mask GeoTIFF to a tinted overlay PNG
     * composited on top of the RGB aerial so the footprint is visible. If
     * the RGB input isn't available, returns a pure stretched mask as PNG.
     */
    public static function maskOverlayOnRgb(string $maskTiffBytes, ?string $rgbTiffBytes): string
    {
        if ($rgbTiffBytes === null || $rgbTiffBytes === '') {
            return self::run($maskTiffBytes, ['-auto-level']);
        }

        $magick = self::binary();
        [$rgbTmp, $maskTmp, $outTmp] = [self::tmp('rgb', 'tif'), self::tmp('mask', 'tif'), self::tmp('out', 'png')];
        try {
            file_put_contents($rgbTmp, $rgbTiffBytes);
            file_put_contents($maskTmp, $maskTiffBytes);

            // Tint the mask blue, preserve alpha from the mask value, then
            // composite over the aerial RGB so only the building area is
            // coloured.
            self::execute([
                $magick, $rgbTmp,
                '(', $maskTmp, '-auto-level',
                    '+level-colors', 'none,#2563EB',
                ')',
                '-compose', 'dissolve', '-define', 'compose:args=55',
                '-composite',
                '-strip', $outTmp,
            ]);
            $bytes = (string) @file_get_contents($outTmp);
            if ($bytes === '') {
                throw new RuntimeException('Mask overlay produced empty output.');
            }
            return $bytes;
        } finally {
            @unlink($rgbTmp);
            @unlink($maskTmp);
            @unlink($outTmp);
        }
    }

    /**
     * Convert a float32 annual-flux GeoTIFF to a blue→red heatmap PNG.
     */
    public static function fluxHeatmap(string $fluxTiffBytes): string
    {
        $magick = self::binary();
        [$in, $grad, $out] = [self::tmp('flux', 'tif'), self::tmp('grad', 'png'), self::tmp('out', 'png')];
        try {
            file_put_contents($in, $fluxTiffBytes);

            // 3-stop palette (blue → yellow → red) built with sparse-color
            // over a 256×1 strip, rotated into a vertical lookup table that
            // ImageMagick `-clut` expects. `shepards` (inverse-distance) is
            // used instead of `barycentric` because our three seed points at
            // (0,0) (128,0) (255,0) are collinear, which makes the barycentric
            // solver fail with "Unsolvable Matrix".
            self::execute([
                $magick, '-size', '256x1', 'xc:',
                '-sparse-color', 'shepards',
                '0,0 #0b3d91 128,0 #f9d423 255,0 #b30000',
                '-rotate', '90', $grad,
            ]);

            self::execute([
                $magick, $in,
                '-auto-level',
                '-colorspace', 'Gray',
                '-depth', '8',
                $grad, '-clut',
                '-strip', $out,
            ]);

            $bytes = (string) @file_get_contents($out);
            if ($bytes === '') {
                throw new RuntimeException('Flux heatmap produced empty output.');
            }
            return $bytes;
        } finally {
            @unlink($in);
            @unlink($grad);
            @unlink($out);
        }
    }

    // -----------------------------------------------------------------------

    /** @param list<string> $extraArgs */
    private static function run(string $tiffBytes, array $extraArgs): string
    {
        $magick = self::binary();
        [$in, $out] = [self::tmp('in', 'tif'), self::tmp('out', 'png')];
        try {
            file_put_contents($in, $tiffBytes);
            self::execute(array_merge([$magick, $in], $extraArgs, [$out]));
            $bytes = (string) @file_get_contents($out);
            if ($bytes === '') {
                throw new RuntimeException('ImageMagick produced empty output.');
            }
            return $bytes;
        } finally {
            @unlink($in);
            @unlink($out);
        }
    }

    private static function binary(): string
    {
        if (self::$magickBin !== null) {
            return self::$magickBin;
        }
        $finder = new ExecutableFinder();
        foreach (['magick', 'convert'] as $candidate) {
            $path = $finder->find($candidate);
            if ($path !== null) {
                return self::$magickBin = $path;
            }
        }
        throw new RuntimeException('ImageMagick not available; install "imagemagick" in the runtime image.');
    }

    private static function tmp(string $prefix, string $ext): string
    {
        $path = tempnam(sys_get_temp_dir(), 'solar_'.$prefix.'_');
        if ($path === false) {
            throw new RuntimeException('Unable to allocate temp file for '.$prefix);
        }
        $withExt = $path.'.'.$ext;
        @rename($path, $withExt);
        return $withExt;
    }

    /** @param list<string> $cmd */
    private static function execute(array $cmd): void
    {
        $proc = new Process($cmd);
        $proc->setTimeout(self::TIMEOUT_SECONDS);
        try {
            $proc->mustRun();
        } catch (ProcessFailedException $e) {
            throw new RuntimeException('ImageMagick failed: '.trim($proc->getErrorOutput()), 0, $e);
        }
    }
}
