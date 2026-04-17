<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Top-level bundle consumed by the HTML/PDF proposal templates.
 */
final class ProposalSummary
{
    /**
     * @param array<string, mixed> $branding
     * @param array<string, mixed> $panelSpec
     */
    public function __construct(
        public readonly string             $projectId,
        public readonly string             $generatedAt,
        public readonly string             $validUntil,
        public readonly string             $customerAddress,
        public readonly array              $branding,
        public readonly array              $panelSpec,
        public readonly BuildingInsights   $building,
        public readonly SolarAnalysis      $analysis,
        public readonly RoofLayout         $layout,
        public readonly PricingBreakdown   $pricing,
        public readonly SavingsForecast    $savings,
        public readonly ?string            $renderImagePath = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'project_id'       => $this->projectId,
            'generated_at'     => $this->generatedAt,
            'valid_until'      => $this->validUntil,
            'customer_address' => $this->customerAddress,
            'branding'         => $this->branding,
            'panel_spec'       => $this->panelSpec,
            'building'         => $this->building->toArray(),
            'analysis'         => $this->analysis->toArray(),
            'layout'           => $this->layout->toArray(),
            'pricing'          => $this->pricing->toArray(),
            'savings'          => $this->savings->toArray(),
            'render_image'     => $this->renderImagePath,
        ];
    }
}
