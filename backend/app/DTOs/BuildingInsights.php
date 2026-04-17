<?php

declare(strict_types=1);

namespace App\DTOs;

final class BuildingInsights
{
    /** @param list<RoofSegment> $segments */
    public function __construct(
        public readonly string $placeId,
        public readonly float  $centerLat,
        public readonly float  $centerLng,
        public readonly string $imageryQuality,
        public readonly string $imageryDate,
        public readonly float  $wholeRoofAreaM2,
        public readonly float  $groundAreaM2,
        public readonly float  $maxSunshineHoursPerYear,
        public readonly float  $carbonOffsetKgPerMwh,
        public readonly int    $solarPotentialMaxPanels,
        public readonly float  $solarPotentialMaxAreaM2,
        public readonly array  $segments,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'place_id' => $this->placeId,
            'center_lat' => $this->centerLat,
            'center_lng' => $this->centerLng,
            'imagery_quality' => $this->imageryQuality,
            'imagery_date' => $this->imageryDate,
            'whole_roof_area_m2' => $this->wholeRoofAreaM2,
            'ground_area_m2' => $this->groundAreaM2,
            'max_sunshine_hours_per_year' => $this->maxSunshineHoursPerYear,
            'carbon_offset_kg_per_mwh' => $this->carbonOffsetKgPerMwh,
            'solar_potential_max_panels' => $this->solarPotentialMaxPanels,
            'solar_potential_max_area_m2' => $this->solarPotentialMaxAreaM2,
            'segments' => array_map(fn (RoofSegment $s) => $s->toArray(), $this->segments),
        ];
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        $segments = [];
        foreach ((array)($d['segments'] ?? []) as $row) {
            $segments[] = RoofSegment::fromArray((array) $row);
        }
        return new self(
            placeId: (string)($d['place_id'] ?? ''),
            centerLat: (float)($d['center_lat'] ?? 0),
            centerLng: (float)($d['center_lng'] ?? 0),
            imageryQuality: (string)($d['imagery_quality'] ?? 'MEDIUM'),
            imageryDate: (string)($d['imagery_date'] ?? ''),
            wholeRoofAreaM2: (float)($d['whole_roof_area_m2'] ?? 0),
            groundAreaM2: (float)($d['ground_area_m2'] ?? 0),
            maxSunshineHoursPerYear: (float)($d['max_sunshine_hours_per_year'] ?? 0),
            carbonOffsetKgPerMwh: (float)($d['carbon_offset_kg_per_mwh'] ?? 0),
            solarPotentialMaxPanels: (int)($d['solar_potential_max_panels'] ?? 0),
            solarPotentialMaxAreaM2: (float)($d['solar_potential_max_area_m2'] ?? 0),
            segments: $segments,
        );
    }
}
