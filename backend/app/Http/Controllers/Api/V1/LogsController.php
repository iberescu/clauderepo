<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\LogsService;
use Illuminate\Http\JsonResponse;

class LogsController extends Controller
{
    public function __construct(private readonly LogsService $logs)
    {
    }

    public function show(string $projectId): JsonResponse
    {
        return response()->json(['data' => $this->logs->forProject($projectId)]);
    }
}
