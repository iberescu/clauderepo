<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Services\ConfigFileService;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function __construct(private readonly ConfigFileService $config)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->config->all()]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $values */
        $values = (array) $request->input('values', []);
        $merged = $this->config->updateSection((string) $request->string('section'), $values);
        return response()->json(['data' => $merged]);
    }
}
