<?php

declare(strict_types=1);

namespace App\DTOs;

final class NormalizedQuery
{
    public function __construct(
        public readonly string $street,
        public readonly string $city,
        public readonly string $country,
        public readonly string $formattedAddress,
        public readonly float  $centerLat,
        public readonly float  $centerLng,
        public readonly float  $confidence,
        public readonly string $placeId = '',
        public readonly string $provider = 'fake',
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'street' => $this->street,
            'city' => $this->city,
            'country' => $this->country,
            'formatted_address' => $this->formattedAddress,
            'center_lat' => $this->centerLat,
            'center_lng' => $this->centerLng,
            'confidence' => $this->confidence,
            'place_id' => $this->placeId,
            'provider' => $this->provider,
        ];
    }
}
