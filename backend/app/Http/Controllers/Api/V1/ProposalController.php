<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Repositories\ProjectFileRepository;
use App\Services\ProposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class ProposalController extends Controller
{
    public function __construct(
        private readonly ProposalService $proposal,
        private readonly ProjectFileRepository $projects,
    ) {
    }

    public function generate(string $projectId): JsonResponse
    {
        $this->ensureExists($projectId);
        $summary = $this->proposal->generate($projectId);
        return response()->json(['data' => $this->payload($projectId, $summary->toArray())]);
    }

    public function show(string $projectId): JsonResponse
    {
        $this->ensureExists($projectId);
        $data = $this->projects->readJson($projectId, 'proposal/proposal_summary.json');
        if ($data === null) {
            abort(BaseResponse::HTTP_NOT_FOUND, 'Proposal not generated yet.');
        }
        return response()->json(['data' => $this->payload($projectId, $data)]);
    }

    public function html(string $projectId): Response
    {
        $this->ensureExists($projectId);
        $html = $this->projects->readText($projectId, 'proposal/proposal.html');
        if ($html === null) {
            abort(BaseResponse::HTTP_NOT_FOUND, 'Proposal not generated yet.');
        }
        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function pdf(string $projectId): Response
    {
        $this->ensureExists($projectId);
        $bytes = $this->projects->readBinary($projectId, 'proposal/proposal.pdf');
        if ($bytes === null) {
            abort(BaseResponse::HTTP_NOT_FOUND, 'Proposal PDF not generated yet.');
        }
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="proposal-'.$projectId.'.pdf"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * @param array<string, mixed> $summary
     * @return array<string, mixed>
     */
    private function payload(string $projectId, array $summary): array
    {
        return [
            'project_id' => $projectId,
            'summary'    => $summary,
            'links'      => [
                'html' => "/api/v1/projects/{$projectId}/proposal/html",
                'pdf'  => "/api/v1/projects/{$projectId}/proposal/pdf",
            ],
        ];
    }

    private function ensureExists(string $projectId): void
    {
        if (!$this->projects->exists($projectId)) {
            abort(BaseResponse::HTTP_NOT_FOUND, "Project {$projectId} not found.");
        }
    }
}
