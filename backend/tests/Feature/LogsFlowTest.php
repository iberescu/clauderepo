<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LogsFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config()->set('solar.fake_providers', true);
    }

    private function buildThroughRender(): string
    {
        $projectId = $this->postJson('/api/v1/projects', [
            'street' => 'Rua das Flores', 'city' => 'Lisbon', 'country' => 'Portugal',
        ])->assertCreated()->json('data.project_id');

        $this->postJson("/api/v1/projects/{$projectId}/select-candidate", ['index' => 2])->assertOk();
        $this->postJson("/api/v1/projects/{$projectId}/analyze-building")->assertOk();
        $this->postJson("/api/v1/projects/{$projectId}/generate-layout")->assertOk();
        $this->postJson("/api/v1/projects/{$projectId}/generate-render")->assertOk();
        return $projectId;
    }

    public function test_logs_endpoint_returns_providers_images_and_api_calls(): void
    {
        $projectId = $this->buildThroughRender();

        $response = $this->getJson("/api/v1/projects/{$projectId}/logs")->assertOk();

        $response->assertJsonStructure(['data' => [
            'project_id',
            'providers' => [
                'fake_providers_flag',
                'services' => ['geo_search', 'candidate_discovery', 'solar_api', 'gemini', 'static_maps'],
                'keys_present',
            ],
            'images',
            'api_calls',
            'progress',
            'errors',
        ]]);

        $response->assertJsonPath('data.project_id', $projectId);
        $response->assertJsonPath('data.providers.fake_providers_flag', true);
        $response->assertJsonPath('data.providers.services.solar_api.kind', 'fake');
        $response->assertJsonPath('data.providers.services.gemini.kind', 'fake');
        $response->assertJsonPath('data.providers.services.static_maps.kind', 'fake');

        $apiCalls = $response->json('data.api_calls');
        $this->assertIsArray($apiCalls);
        $this->assertNotEmpty($apiCalls, 'expected at least one api call');
        foreach ($apiCalls as $row) {
            $this->assertArrayHasKey('label', $row);
            $this->assertArrayHasKey('status', $row);
            $this->assertArrayHasKey('kind', $row);
            $this->assertContains($row['kind'], ['fake', 'live']);
        }

        $labels = array_column($apiCalls, 'label');
        $this->assertContains('fake.staticMaps', $labels, 'expected fake static maps call to be logged');
        $geminiLabels = array_filter($labels, fn ($l) => str_starts_with($l, 'gemini.'));
        $this->assertNotEmpty($geminiLabels, 'expected gemini enhance calls to be logged');

        $images = collect($response->json('data.images'));
        $this->assertTrue($images->contains(fn ($img) => $img['path'] === 'render/roof_render.png' && $img['kind'] === 'fake'));
        $this->assertTrue($images->contains(fn ($img) => $img['path'] === 'render/roof_base.png' && $img['kind'] === 'deterministic'));
        $this->assertTrue($images->contains(fn ($img) => $img['path'] === 'render/real_aerial_overlay.png'));
    }

    public function test_logs_endpoint_works_for_empty_project(): void
    {
        $projectId = $this->postJson('/api/v1/projects', [
            'street' => 'Rua das Flores', 'city' => 'Lisbon', 'country' => 'Portugal',
        ])->assertCreated()->json('data.project_id');

        $response = $this->getJson("/api/v1/projects/{$projectId}/logs")->assertOk();
        $response->assertJsonPath('data.project_id', $projectId);
        $this->assertIsArray($response->json('data.api_calls'));
        $this->assertIsArray($response->json('data.images'));
    }
}
