<?php

declare(strict_types=1);

namespace App\DTOs;

final class ProjectInput
{
    public function __construct(
        public readonly string $street,
        public readonly string $city,
        public readonly string $country,
    ) {
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return ['street' => $this->street, 'city' => $this->city, 'country' => $this->country];
    }
}
