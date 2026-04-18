<?php

use App\Http\Controllers\Api\V1\BuildingController;
use App\Http\Controllers\Api\V1\CandidateController;
use App\Http\Controllers\Api\V1\LayoutController;
use App\Http\Controllers\Api\V1\PricingController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProposalController;
use App\Http\Controllers\Api\V1\RenderController;
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

    Route::post('/projects/{projectId}/analyze-building', [BuildingController::class, 'analyze']);
    Route::get('/projects/{projectId}/analysis', [BuildingController::class, 'show']);
    Route::get('/projects/{projectId}/solar/images/{name}', [BuildingController::class, 'image'])
        ->where('name', '(rgb|mask|flux)\.png');

    Route::post('/projects/{projectId}/generate-layout', [LayoutController::class, 'generate']);
    Route::get('/projects/{projectId}/layout', [LayoutController::class, 'show']);

    Route::post('/projects/{projectId}/generate-render', [RenderController::class, 'generate']);
    Route::get('/projects/{projectId}/render', [RenderController::class, 'show']);
    Route::get('/projects/{projectId}/render/images/{name}', [RenderController::class, 'image'])
        ->where('name', 'roof_(base|overlay|render|render_3d)\.png');

    Route::post('/projects/{projectId}/generate-pricing', [PricingController::class, 'generate']);
    Route::get('/projects/{projectId}/pricing', [PricingController::class, 'show']);

    Route::post('/projects/{projectId}/generate-proposal', [ProposalController::class, 'generate']);
    Route::get('/projects/{projectId}/proposal', [ProposalController::class, 'show']);
    Route::get('/projects/{projectId}/proposal/html', [ProposalController::class, 'html']);
    Route::get('/projects/{projectId}/proposal/pdf', [ProposalController::class, 'pdf']);

    Route::get('/settings', [SettingsController::class, 'index']);
    Route::post('/settings', [SettingsController::class, 'update']);
});
