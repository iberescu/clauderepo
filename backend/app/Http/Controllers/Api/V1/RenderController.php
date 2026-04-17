<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Repositories\ProjectFileRepository;
use App\Services\RenderingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class RenderController extends Controller
{
    public function __construct(
        private readonly RenderingService $rendering,
        private readonly ProjectFileRepository $projects,
    ) {
    }

    public function generate(string $projectId): JsonResponse
    {
        $this->ensureExists($projectId);
        $notes = $this->rendering->generate($projectId);
        return response()->json(['data' => $this->payload($projectId, $notes)]);
    }

    public function show(string $projectId): JsonResponse
    {
        $this->ensureExists($projectId);
        $notes = $this->projects->readText($projectId, 'render/render_notes.txt') ?? '';
        return response()->json(['data' => $this->payload($projectId, $notes)]);
    }

    public function image(string $projectId, string $name): Response
    {
        $this->ensureExists($projectId);
        $allowed = ['roof_base.png', 'roof_overlay.png', 'roof_render.png'];
        if (!in_array($name, $allowed, true)) {
            abort(404);
        }
        $bytes = $this->projects->readBinary($projectId, 'render/'.$name);
        if ($bytes === null) {
            abort(404, 'Image not generated yet.');
        }
        return response($bytes, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(string $projectId, string $notes): array
    {
        $base = "/api/v1/projects/{$projectId}/render/images/";
        return [
            'project_id' => $projectId,
            'notes' => $notes,
            'prompt' => $this->projects->readText($projectId, 'render/gemini_prompt.txt'),
            'images' => [
                'roof_base'    => $this->projects->readBinary($projectId, 'render/roof_base.png')    ? $base.'roof_base.png'    : null,
                'roof_overlay' => $this->projects->readBinary($projectId, 'render/roof_overlay.png') ? $base.'roof_overlay.png' : null,
                'roof_render'  => $this->projects->readBinary($projectId, 'render/roof_render.png')  ? $base.'roof_render.png'  : null,
            ],
        ];
    }

    private function ensureExists(string $projectId): void
    {
        if (!$this->projects->exists($projectId)) {
            abort(404, "Project {$projectId} not found.");
        }
    }
}
