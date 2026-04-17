<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Candidate;
use App\DTOs\NormalizedQuery;

interface CandidateDiscoveryServiceInterface
{
    /**
     * Discover candidate buildings for the given normalized query.
     * Implementations write raw payloads to candidates/discovery_raw.txt.
     *
     * @return list<Candidate>
     */
    public function discover(string $projectId, NormalizedQuery $query): array;
}
