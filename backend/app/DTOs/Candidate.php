<?php

declare(strict_types=1);

namespace App\DTOs;

final class Candidate
{
    public function __construct(
        public readonly string $id,
        public readonly int    $index,
        public readonly string $formattedAddress,
        public readonly float  $lat,
        public readonly float  $lng,
        public readonly float  $confidence,
        public readonly bool   $solarSupported,
        public readonly string $notes = '',
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'index' => $this->index,
            'formatted_address' => $this->formattedAddress,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'confidence' => $this->confidence,
            'solar_supported' => $this->solarSupported,
            'notes' => $this->notes,
        ];
    }
}
