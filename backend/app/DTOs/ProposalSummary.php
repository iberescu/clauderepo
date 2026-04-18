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
        public readonly ?string            $render3dImagePath = null,
        public readonly ?string            $aerialImagePath = null,
        public readonly ?string            $fluxImagePath = null,
        public readonly ?string            $realAerial3dImagePath = null,
        public readonly ?string            $staticMap3dImagePath = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'project_id'             => $this->projectId,
            'generated_at'           => $this->generatedAt,
            'valid_until'            => $this->validUntil,
            'customer_address'       => $this->customerAddress,
            'branding'               => $this->branding,
            'panel_spec'             => $this->panelSpec,
            'building'               => $this->building->toArray(),
            'analysis'               => $this->analysis->toArray(),
            'layout'                 => $this->layout->toArray(),
            'pricing'                => $this->pricing->toArray(),
            'savings'                => $this->savings->toArray(),
            'render_image'           => $this->renderImagePath,
            'render_3d_image'        => $this->render3dImagePath,
            'aerial_image'           => $this->aerialImagePath,
            'flux_image'             => $this->fluxImagePath,
            'real_aerial_3d_image'   => $this->realAerial3dImagePath,
            'static_map_3d_image'    => $this->staticMap3dImagePath,
        ];
    }
}
