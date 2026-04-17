<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PricingFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config()->set('solar.fake_providers', true);
    }

    private function buildUpToLayout(): string
    {
        $projectId = $this->postJson('/api/v1/projects', [
            'street' => 'Rua das Flores', 'city' => 'Lisbon', 'country' => 'Portugal',
        ])->assertCreated()->json('data.project_id');

        $this->postJson("/api/v1/projects/{$projectId}/select-candidate", ['index' => 2])->assertOk();
        $this->postJson("/api/v1/projects/{$projectId}/analyze-building")->assertOk();
        $this->postJson("/api/v1/projects/{$projectId}/generate-layout")->assertOk();
        return $projectId;
    }

    public function test_generate_pricing_produces_breakdown_and_files(): void
    {
        $projectId = $this->buildUpToLayout();

        $response = $this->postJson("/api/v1/projects/{$projectId}/generate-pricing")->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'project_id',
                'pricing' => [
                    'currency', 'line_items', 'equipment_cost', 'labor_cost',
                    'subtotal', 'margin', 'discount', 'net_before_vat', 'vat',
                    'total', 'price_per_kwp', 'price_per_panel',
                    'total_panels', 'total_kwp',
                ],
                'summary',
            ],
        ]);

        $pricing = $response->json('data.pricing');
        $this->assertGreaterThan(0, $pricing['total']);
        $this->assertSame('EUR', $pricing['currency']);
        $this->assertGreaterThan(0, count($pricing['line_items']));

        // Math: subtotal = equipment + labor (within rounding).
        $this->assertEqualsWithDelta(
            $pricing['equipment_cost'] + $pricing['labor_cost'],
            $pricing['subtotal'],
            0.05,
            'subtotal should equal equipment + labour',
        );

        $disk = Storage::disk('local');
        $this->assertTrue($disk->exists("projects/{$projectId}/pricing/pricing_breakdown.json"));
        $this->assertTrue($disk->exists("projects/{$projectId}/pricing/pricing_breakdown.txt"));
        $this->assertTrue($disk->exists("projects/{$projectId}/pricing/assumptions.txt"));

        $this->getJson("/api/v1/projects/{$projectId}/status")
            ->assertJsonPath('data.current.status', 'pricing_ready');

        // show endpoint returns the same numbers
        $this->getJson("/api/v1/projects/{$projectId}/pricing")
            ->assertOk()
            ->assertJsonPath('data.pricing.total', $pricing['total']);
    }

    public function test_generate_pricing_fails_without_layout(): void
    {
        $projectId = $this->postJson('/api/v1/projects', [
            'street' => 'Rua das Flores', 'city' => 'Lisbon', 'country' => 'Portugal',
        ])->assertCreated()->json('data.project_id');

        $this->postJson("/api/v1/projects/{$projectId}/generate-pricing")->assertStatus(500);
    }

    public function test_pricing_respects_config_overrides(): void
    {
        $projectId = $this->buildUpToLayout();

        // Drop margin + VAT to 0 via the settings API.
        $this->postJson('/api/v1/settings', [
            'section' => 'pricing',
            'values'  => ['margin_pct' => 0.0, 'vat_pct' => 0.0, 'discount_pct' => 0.0],
        ])->assertOk();

        $response = $this->postJson("/api/v1/projects/{$projectId}/generate-pricing")->assertOk();
        $pricing = $response->json('data.pricing');

        $this->assertEqualsWithDelta($pricing['subtotal'], $pricing['total'], 0.05);
        $this->assertEqualsWithDelta(0.0, $pricing['margin'], 0.01);
        $this->assertEqualsWithDelta(0.0, $pricing['vat'], 0.01);
    }
}
