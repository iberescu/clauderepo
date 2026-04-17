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
}
