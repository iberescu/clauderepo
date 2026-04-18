<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\BuildingInsights;
use App\DTOs\Candidate;

interface SolarApiServiceInterface
{
    /**
     * Fetch Solar API Building Insights for the candidate. Implementations are
     * expected to write the raw provider payload to
     * building/building_insights_raw.txt.
     */
    public function buildingInsights(string $projectId, Candidate $candidate): BuildingInsights;

    /**
     * Fetch Solar API Data Layers (annual flux + mask summaries) for the same
     * candidate and write a short text summary to
     * solar/data_layers_raw.txt + solar/annual_flux_summary.txt.
     *
     * @return array{annual_flux_max: float, annual_flux_mean: float, mask_coverage: float}
     */
    public function dataLayersSummary(string $projectId, Candidate $candidate): array;

    /**
     * Download the RGB aerial GeoTIFF exposed by Data Layers, convert it to a
     * browser-friendly PNG and persist it under solar/images/. Returns the map
     * of saved images keyed by name (e.g. ['rgb' => 'solar/images/rgb.png']).
     * Failures should log via the status service and return an empty array so
     * the pipeline can continue with the deterministic artefacts.
     *
     * @return array<string, string>
     */
    public function downloadImagery(string $projectId, Candidate $candidate): array;
}
