<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SelectCandidateRequest;
use App\Repositories\ProjectFileRepository;
use App\Services\ProjectOrchestrationService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CandidateController extends Controller
{
    public function __construct(
        private readonly ProjectOrchestrationService $orchestrator,
        private readonly ProjectFileRepository $projects,
    ) {
    }

    public function index(string $projectId): JsonResponse
    {
        $this->ensureExists($projectId);
        return response()->json([
            'data' => $this->projects->readJson($projectId, 'candidates/candidates.json') ?? [],
            'selected' => $this->projects->readJson($projectId, 'building/selected_building.json'),
        ]);
    }

    public function select(SelectCandidateRequest $request, string $projectId): JsonResponse
    {
        $this->ensureExists($projectId);
        $candidate = $this->orchestrator->selectCandidate($projectId, $request->index());
        return response()->json(['data' => $candidate->toArray()]);
    }

    private function ensureExists(string $projectId): void
    {
        if (!$this->projects->exists($projectId)) {
            abort(Response::HTTP_NOT_FOUND, "Project {$projectId} not found.");
        }
    }
}
