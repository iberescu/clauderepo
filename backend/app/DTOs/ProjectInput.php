<?php

declare(strict_types=1);

namespace App\DTOs;

final class ProjectInput
{
    public function __construct(
        public readonly string $street,
        public readonly string $city,
        public readonly string $country,
        public readonly ?float $lat = null,
        public readonly ?float $lng = null,
    ) {
    }

    public function hasCoordinates(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'street'  => $this->street,
            'city'    => $this->city,
            'country' => $this->country,
            'lat'     => $this->lat,
            'lng'     => $this->lng,
        ];
    }
}
