<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\DTOs\Candidate;
use App\DTOs\NormalizedQuery;
use App\Repositories\ProjectFileRepository;
use App\Services\Contracts\CandidateDiscoveryServiceInterface;
use App\Services\Fake\FakeCandidateDiscoveryService;
use App\Services\StatusFileService;

/**
 * Real candidate discovery via Google Places. For the MVP the implementation
 * falls back to the deterministic sampler when the live APIs are disabled;
 * the main purpose of this class is to keep the interface stable so a proper
 * nearby-search implementation can be dropped in later.
 */
final class GoogleCandidateDiscoveryService implements CandidateDiscoveryServiceInterface
{
    public function __construct(
        private readonly ProjectFileRepository $projects,
        private readonly StatusFileService $status,
        private readonly FakeCandidateDiscoveryService $fallback,
    ) {
    }

    public function discover(string $projectId, NormalizedQuery $query): array
    {
        $key = (string) config('solar.google.maps_api_key');
        if ($key === '') {
            $this->status->progress($projectId, 'candidates.discover falling back to deterministic sampler (no API key)');
            return $this->fallback->discover($projectId, $query);
        }

        // A production implementation would hit Places Nearby / Text Search here;
        // for the MVP we still use the deterministic sampler so results remain
        // reproducible from saved files. The key presence is logged for debugging.
        $this->status->progress($projectId, 'candidates.discover using deterministic sampler (google provider stub)');

        return $this->fallback->discover($projectId, $query);
    }
}
