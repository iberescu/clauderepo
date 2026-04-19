<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

final class Slug
{
    public static function make(string ...$parts): string
    {
        $joined = trim(implode('-', array_map(fn ($p) => trim((string) $p), $parts)), '-');

        return Str::slug($joined) ?: 'unknown';
    }

    public static function projectId(string $street, string $city, string $country, ?\DateTimeImmutable $at = null): string
    {
        $at = $at ?? new \DateTimeImmutable('now');
        $stamp = $at->format('Ymd_His');
        $slug  = self::make($street, $city, $country);

        return "{$stamp}_{$slug}";
    }

    public static function projectIdFromCoords(float $lat, float $lng, ?\DateTimeImmutable $at = null): string
    {
        $at = $at ?? new \DateTimeImmutable('now');
        $stamp = $at->format('Ymd_His');
        $slug  = 'coord-'.self::coordPart($lat).'-'.self::coordPart($lng);

        return "{$stamp}_{$slug}";
    }

    private static function coordPart(float $v): string
    {
        $sign = $v < 0 ? 'n' : 'p';
        return $sign.str_replace('.', 'p', number_format(abs($v), 5, '.', ''));
    }
}
