<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateProjectRequest;
use App\Repositories\ProjectFileRepository;
use App\Services\ProjectOrchestrationService;
use App\Services\StatusFileService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectOrchestrationService $orchestrator,
        private readonly ProjectFileRepository $projects,
        private readonly StatusFileService $status,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->projects->listProjectIds(),
        ]);
    }

    public function store(CreateProjectRequest $request): JsonResponse
    {
        $projectId = $this->orchestrator->createProject($request->toDto());
        return response()->json(['data' => $this->summary($projectId)], Response::HTTP_CREATED);
    }

    public function show(string $projectId): JsonResponse
    {
        $this->ensureExists($projectId);
        return response()->json(['data' => $this->summary($projectId)]);
    }

    public function status(string $projectId): JsonResponse
    {
        $this->ensureExists($projectId);
        return response()->json([
            'data' => [
                'project_id' => $projectId,
                'current'    => $this->status->currentStatus($projectId),
                'progress'   => $this->status->progressLines($projectId),
                'errors'     => $this->status->errorLines($projectId),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function summary(string $projectId): array
    {
        return [
            'project_id' => $projectId,
            'request'    => $this->projects->readJson($projectId, 'input/request.json'),
            'normalized_query' => $this->projects->readJson($projectId, 'input/normalized_query.json'),
            'candidates' => $this->projects->readJson($projectId, 'candidates/candidates.json') ?? [],
            'selected_candidate' => $this->projects->readJson($projectId, 'building/selected_building.json'),
            'status' => $this->status->currentStatus($projectId),
        ];
    }

    private function ensureExists(string $projectId): void
    {
        if (!$this->projects->exists($projectId)) {
            abort(Response::HTTP_NOT_FOUND, "Project {$projectId} not found.");
        }
    }
}
