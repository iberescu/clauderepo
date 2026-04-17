<?php

declare(strict_types=1);

namespace App\DTOs;

final class SolarAnalysis
{
    public function __construct(
        public readonly float $usableRoofAreaM2,
        public readonly float $averagePitchDeg,
        public readonly float $dominantAzimuthDeg,
        public readonly float $averageSunshineHoursPerYear,
        public readonly float $shadingScore,
        public readonly float $confidence,
        public readonly string $imageryQuality,
        public readonly string $imageryDate,
        public readonly int   $usableSegments,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'usable_roof_area_m2' => $this->usableRoofAreaM2,
            'average_pitch_deg' => $this->averagePitchDeg,
            'dominant_azimuth_deg' => $this->dominantAzimuthDeg,
            'average_sunshine_hours_per_year' => $this->averageSunshineHoursPerYear,
            'shading_score' => $this->shadingScore,
            'confidence' => $this->confidence,
            'imagery_quality' => $this->imageryQuality,
            'imagery_date' => $this->imageryDate,
            'usable_segments' => $this->usableSegments,
        ];
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            usableRoofAreaM2: (float)($d['usable_roof_area_m2'] ?? 0),
            averagePitchDeg: (float)($d['average_pitch_deg'] ?? 0),
            dominantAzimuthDeg: (float)($d['dominant_azimuth_deg'] ?? 0),
            averageSunshineHoursPerYear: (float)($d['average_sunshine_hours_per_year'] ?? 0),
            shadingScore: (float)($d['shading_score'] ?? 0),
            confidence: (float)($d['confidence'] ?? 0),
            imageryQuality: (string)($d['imagery_quality'] ?? 'MEDIUM'),
            imageryDate: (string)($d['imagery_date'] ?? ''),
            usableSegments: (int)($d['usable_segments'] ?? 0),
        );
    }
}
