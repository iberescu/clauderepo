<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Repositories\ProjectFileRepository;
use App\Services\SolarAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
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

    public function image(string $projectId, string $name): HttpResponse
    {
        $this->ensureExists($projectId);
        $allowed = ['rgb.png', 'mask.png', 'flux.png'];
        if (!in_array($name, $allowed, true)) {
            abort(404);
        }
        $bytes = $this->projects->readBinary($projectId, 'solar/images/'.$name);
        if ($bytes === null) {
            abort(404, 'Imagery not generated yet.');
        }
        return response($bytes, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * @param array<string, mixed> $analysisArray
     * @return array<string, mixed>
     */
    private function payload(string $projectId, array $analysisArray): array
    {
        $imageryBase = "/api/v1/projects/{$projectId}/solar/images/";
        $imagery = [];
        foreach (['rgb', 'mask', 'flux'] as $name) {
            if ($this->projects->readBinary($projectId, 'solar/images/'.$name.'.png') !== null) {
                $imagery[$name] = $imageryBase.$name.'.png';
            }
        }
        return [
            'project_id' => $projectId,
            'analysis' => $analysisArray,
            'building' => $this->projects->readJson($projectId, 'building/building_insights.json'),
            'shade_summary' => $this->projects->readText($projectId, 'solar/shade_summary.txt'),
            'imagery' => $imagery,
        ];
    }

    private function ensureExists(string $projectId): void
    {
        if (!$this->projects->exists($projectId)) {
            abort(Response::HTTP_NOT_FOUND, "Project {$projectId} not found.");
        }
    }
}
