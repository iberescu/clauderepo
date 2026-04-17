<?php

declare(strict_types=1);

namespace App\DTOs;

final class RoofSegment
{
    public function __construct(
        public readonly int    $index,
        public readonly float  $pitchDegrees,
        public readonly float  $azimuthDegrees,
        public readonly float  $areaM2,
        public readonly float  $widthM,
        public readonly float  $heightM,
        public readonly float  $centerLat,
        public readonly float  $centerLng,
        public readonly float  $sunshineHoursPerYear,
        public readonly float  $sunshineP50,
        public readonly float  $shadePenalty,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'pitch_deg' => $this->pitchDegrees,
            'azimuth_deg' => $this->azimuthDegrees,
            'area_m2' => $this->areaM2,
            'width_m' => $this->widthM,
            'height_m' => $this->heightM,
            'center_lat' => $this->centerLat,
            'center_lng' => $this->centerLng,
            'sunshine_hours_per_year' => $this->sunshineHoursPerYear,
            'sunshine_p50' => $this->sunshineP50,
            'shade_penalty' => $this->shadePenalty,
        ];
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            index: (int)($d['index'] ?? 0),
            pitchDegrees: (float)($d['pitch_deg'] ?? 0),
            azimuthDegrees: (float)($d['azimuth_deg'] ?? 0),
            areaM2: (float)($d['area_m2'] ?? 0),
            widthM: (float)($d['width_m'] ?? 0),
            heightM: (float)($d['height_m'] ?? 0),
            centerLat: (float)($d['center_lat'] ?? 0),
            centerLng: (float)($d['center_lng'] ?? 0),
            sunshineHoursPerYear: (float)($d['sunshine_hours_per_year'] ?? 0),
            sunshineP50: (float)($d['sunshine_p50'] ?? 0),
            shadePenalty: (float)($d['shade_penalty'] ?? 0),
        );
    }
}
