<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Repositories\ProjectFileRepository;
use App\Services\SolarAnalysisService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class BuildingController extends Controller
{
    public function __construct(
        private readonly SolarAnalysisService $analysis,
        private readonly ProjectFileRepository $projects,
    ) {
    }

    public function analyze(string $projectId): JsonResponse
    {
        $this->ensureExists($projectId);
        $summary = $this->analysis->analyze($projectId);
        return response()->json(['data' => $this->payload($projectId, $summary->toArray())]);
    }

    public function show(string $projectId): JsonResponse
    {
        $this->ensureExists($projectId);
        $analysis = $this->projects->readSolarAnalysis($projectId);
        if ($analysis === null) {
            abort(Response::HTTP_NOT_FOUND, 'Analysis not available yet.');
        }
        return response()->json(['data' => $this->payload($projectId, $analysis->toArray())]);
    }

    /**
     * @param array<string, mixed> $analysisArray
     * @return array<string, mixed>
     */
    private function payload(string $projectId, array $analysisArray): array
    {
        return [
            'project_id' => $projectId,
            'analysis' => $analysisArray,
            'building' => $this->projects->readJson($projectId, 'building/building_insights.json'),
            'shade_summary' => $this->projects->readText($projectId, 'solar/shade_summary.txt'),
        ];
    }

    private function ensureExists(string $projectId): void
    {
        if (!$this->projects->exists($projectId)) {
            abort(Response::HTTP_NOT_FOUND, "Project {$projectId} not found.");
        }
    }
}
