<?php

use App\Http\Controllers\Api\V1\CandidateController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\SettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => ['status' => 'ok']);

    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{projectId}', [ProjectController::class, 'show']);
    Route::get('/projects/{projectId}/status', [ProjectController::class, 'status']);

    Route::get('/projects/{projectId}/candidates', [CandidateController::class, 'index']);
    Route::post('/projects/{projectId}/select-candidate', [CandidateController::class, 'select']);

    Route::get('/settings', [SettingsController::class, 'index']);
    Route::post('/settings', [SettingsController::class, 'update']);
});
