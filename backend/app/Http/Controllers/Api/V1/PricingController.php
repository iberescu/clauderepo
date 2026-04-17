<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Repositories\ProjectFileRepository;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PricingController extends Controller
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly ProjectFileRepository $projects,
    ) {
    }

    public function generate(string $projectId): JsonResponse
    {
        $this->ensureExists($projectId);
        $breakdown = $this->pricing->generate($projectId);
        return response()->json(['data' => $this->payload($projectId, $breakdown->toArray())]);
    }

    public function show(string $projectId): JsonResponse
    {
        $this->ensureExists($projectId);
        $data = $this->projects->readJson($projectId, 'pricing/pricing_breakdown.json');
        if ($data === null) {
            abort(Response::HTTP_NOT_FOUND, 'Pricing not generated yet.');
        }
        return response()->json(['data' => $this->payload($projectId, $data)]);
    }

    /**
     * @param array<string, mixed> $pricing
     * @return array<string, mixed>
     */
    private function payload(string $projectId, array $pricing): array
    {
        return [
            'project_id' => $projectId,
            'pricing'    => $pricing,
            'summary'    => $this->projects->readText($projectId, 'pricing/pricing_breakdown.txt'),
        ];
    }

    private function ensureExists(string $projectId): void
    {
        if (!$this->projects->exists($projectId)) {
            abort(Response::HTTP_NOT_FOUND, "Project {$projectId} not found.");
        }
    }
}
