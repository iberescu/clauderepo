<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Repositories\ProjectFileRepository;
use App\Services\SolarAnalysisService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class LayoutController extends Controller
{
    public function __construct(
        private readonly SolarAnalysisService $analysis,
        private readonly ProjectFileRepository $projects,
    ) {
    }

    public function generate(string $projectId): JsonResponse
    {
        $this->ensureExists($projectId);
        $layout = $this->analysis->generateLayout($projectId);
        return response()->json(['data' => $this->payload($projectId, $layout->toArray())]);
    }

    public function show(string $projectId): JsonResponse
    {
        $this->ensureExists($projectId);
        $data = $this->projects->readJson($projectId, 'layout/layout.json');
        if ($data === null) {
            abort(Response::HTTP_NOT_FOUND, 'Layout not generated yet.');
        }
        return response()->json(['data' => $this->payload($projectId, $data)]);
    }

    /**
     * @param array<string, mixed> $layoutArray
     * @return array<string, mixed>
     */
    private function payload(string $projectId, array $layoutArray): array
    {
        return [
            'project_id' => $projectId,
            'layout' => $layoutArray,
            'panel_coordinates' => $this->projects->readText($projectId, 'layout/panel_coordinates.txt'),
        ];
    }

    private function ensureExists(string $projectId): void
    {
        if (!$this->projects->exists($projectId)) {
            abort(Response::HTTP_NOT_FOUND, "Project {$projectId} not found.");
        }
    }
}
