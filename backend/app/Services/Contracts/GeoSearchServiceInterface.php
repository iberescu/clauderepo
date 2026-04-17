<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\NormalizedQuery;
use App\DTOs\ProjectInput;

interface GeoSearchServiceInterface
{
    /**
     * Resolve the user's raw street query into a canonical address + centroid.
     * Raw provider responses (when applicable) should be written to
     * input/geocode_raw.txt by the implementation.
     */
    public function resolve(string $projectId, ProjectInput $input): NormalizedQuery;
}
