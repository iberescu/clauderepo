<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Candidate;
use App\DTOs\ProjectInput;
use App\Repositories\ProjectFileRepository;
use App\Services\Contracts\CandidateDiscoveryServiceInterface;
use App\Services\Contracts\GeoSearchServiceInterface;
use App\Support\Slug;
use RuntimeException;

/**
 * Thin orchestrator for the "create project + resolve street + discover
 * candidates" happy path. Keeps the controller free of business logic.
 */
final class ProjectOrchestrationService
{
    public function __construct(
        private readonly ProjectFileRepository $projects,
        private readonly StatusFileService $status,
        private readonly GeoSearchServiceInterface $geo,
        private readonly CandidateDiscoveryServiceInterface $candidates,
    ) {
    }

    public function createProject(ProjectInput $input): string
    {
        $createdAt = new \DateTimeImmutable('now');
        $projectId = Slug::projectId($input->street, $input->city, $input->country, $createdAt);

        // Collisions are rare (second precision) but possible in tests.
        $suffix = 1;
        $original = $projectId;
        while ($this->projects->exists($projectId)) {
            $projectId = $original.'_'.$suffix++;
        }

        $this->projects->create($projectId);
        $this->projects->saveRequest($projectId, $input, $createdAt);
        $this->status->setStatus($projectId, StatusFileService::STATUS_CREATED);

        try {
            $this->status->setStatus($projectId, StatusFileService::STATUS_SEARCHING);
            $query = $this->geo->resolve($projectId, $input);
            $this->projects->saveNormalizedQuery($projectId, $query);

            $candidates = $this->candidates->discover($projectId, $query);
            $this->projects->saveCandidates($projectId, $candidates);
            $this->status->setStatus($projectId, StatusFileService::STATUS_CANDIDATES_READY,
                'candidates='.count($candidates));
        } catch (\Throwable $e) {
            $this->status->error($projectId, 'project.create failed', $e);
            $this->status->setStatus($projectId, StatusFileService::STATUS_FAILED, $e->getMessage());
            throw $e;
        }

        return $projectId;
    }

    public function selectCandidate(string $projectId, int $index): Candidate
    {
        $candidates = $this->projects->readCandidates($projectId);
        if ($candidates === []) {
            throw new RuntimeException('No candidates available for project '.$projectId);
        }

        $match = null;
        foreach ($candidates as $c) {
            if ($c->index === $index) {
                $match = $c;
                break;
            }
        }
        if ($match === null) {
            throw new RuntimeException("Candidate index {$index} not found.");
        }

        $this->projects->saveSelectedCandidate($projectId, $match);
        $this->status->setStatus($projectId, StatusFileService::STATUS_CANDIDATE_SELECTED,
            "index={$match->index} address={$match->formattedAddress}");

        return $match;
    }
}
