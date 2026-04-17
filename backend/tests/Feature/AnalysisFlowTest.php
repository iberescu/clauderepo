<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnalysisFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config()->set('solar.fake_providers', true);
    }

    private function createProject(): string
    {
        return $this->postJson('/api/v1/projects', [
            'street' => 'Rua das Flores', 'city' => 'Lisbon', 'country' => 'Portugal',
        ])->assertCreated()->json('data.project_id');
    }

    public function test_analyze_requires_selected_candidate(): void
    {
        $projectId = $this->createProject();
        $this->postJson("/api/v1/projects/{$projectId}/analyze-building")->assertStatus(500);
    }

    public function test_full_analysis_and_layout_flow(): void
    {
        $projectId = $this->createProject();
        $this->postJson("/api/v1/projects/{$projectId}/select-candidate", ['index' => 2])->assertOk();

        $analysis = $this->postJson("/api/v1/projects/{$projectId}/analyze-building")->assertOk();
        $analysis->assertJsonPath('data.project_id', $projectId);
        $analysis->assertJsonStructure(['data' => ['analysis' => [
            'usable_roof_area_m2', 'average_pitch_deg', 'dominant_azimuth_deg',
            'average_sunshine_hours_per_year', 'shading_score', 'confidence',
            'imagery_quality', 'usable_segments',
        ]]]);

        $disk = Storage::disk('local');
        $disk->assertExists("projects/{$projectId}/building/building_summary.txt");
        $disk->assertExists("projects/{$projectId}/building/roof_segments.txt");
        $disk->assertExists("projects/{$projectId}/building/building_insights_raw.txt");
        $disk->assertExists("projects/{$projectId}/solar/solar_analysis.txt");
        $disk->assertExists("projects/{$projectId}/solar/annual_flux_summary.txt");
        $disk->assertExists("projects/{$projectId}/solar/shade_summary.txt");

        $this->getJson("/api/v1/projects/{$projectId}/analysis")
            ->assertOk()
            ->assertJsonPath('data.project_id', $projectId);

        $layout = $this->postJson("/api/v1/projects/{$projectId}/generate-layout")->assertOk();
        $layout->assertJsonStructure(['data' => ['layout' => [
            'total_panels', 'total_kwp', 'annual_kwh', 'segments',
        ]]]);

        $totalPanels = (int) $layout->json('data.layout.total_panels');
        $this->assertGreaterThan(0, $totalPanels);

        $disk->assertExists("projects/{$projectId}/layout/layout_summary.txt");
        $disk->assertExists("projects/{$projectId}/layout/panel_coordinates.txt");
        $disk->assertExists("projects/{$projectId}/layout/layout_debug.txt");

        $this->getJson("/api/v1/projects/{$projectId}/layout")
            ->assertOk()
            ->assertJsonPath('data.layout.total_panels', $totalPanels);

        $this->getJson("/api/v1/projects/{$projectId}/status")
            ->assertJsonPath('data.current.status', 'layout_ready');
    }

    public function test_layout_without_analysis_is_rejected(): void
    {
        $projectId = $this->createProject();
        $this->postJson("/api/v1/projects/{$projectId}/select-candidate", ['index' => 1])->assertOk();
        $this->postJson("/api/v1/projects/{$projectId}/generate-layout")->assertStatus(500);
    }
}
