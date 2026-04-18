<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RenderFlowTest extends TestCase
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

    public function test_render_produces_three_valid_pngs(): void
    {
        $projectId = $this->buildUpToLayout();

        $response = $this->postJson("/api/v1/projects/{$projectId}/generate-render")->assertOk();
        $response->assertJsonStructure(['data' => ['notes', 'prompt', 'prompt_3d',
            'images' => ['roof_base', 'roof_overlay', 'roof_render', 'roof_render_3d']]]);

        $disk = Storage::disk('local');
        foreach (['roof_base.png', 'roof_overlay.png', 'roof_render.png', 'roof_render_3d.png'] as $name) {
            $path = "projects/{$projectId}/render/{$name}";
            $this->assertTrue($disk->exists($path), "Missing {$name}");
            $bytes = $disk->get($path);
            $this->assertGreaterThan(500, strlen((string) $bytes), "{$name} seems empty");
            $this->assertSame("\x89PNG\r\n\x1a\n", substr((string) $bytes, 0, 8), "{$name} is not a PNG");
        }

        $disk->assertExists("projects/{$projectId}/render/gemini_prompt.txt");
        $disk->assertExists("projects/{$projectId}/render/gemini_prompt_3d.txt");
        $disk->assertExists("projects/{$projectId}/render/render_notes.txt");

        $this->getJson("/api/v1/projects/{$projectId}/status")
            ->assertJsonPath('data.current.status', 'render_ready');
    }

    public function test_image_endpoint_streams_png(): void
    {
        $projectId = $this->buildUpToLayout();
        $this->postJson("/api/v1/projects/{$projectId}/generate-render")->assertOk();

        foreach (['roof_overlay.png', 'roof_render_3d.png'] as $name) {
            $response = $this->get("/api/v1/projects/{$projectId}/render/images/{$name}");
            $response->assertOk();
            $response->assertHeader('Content-Type', 'image/png');
            $this->assertSame("\x89PNG\r\n\x1a\n", substr((string) $response->getContent(), 0, 8));
        }
    }

    public function test_generate_render_fails_without_layout(): void
    {
        $projectId = $this->postJson('/api/v1/projects', [
            'street' => 'A', 'city' => 'B', 'country' => 'C',
        ])->json('data.project_id') ?? $this->postJson('/api/v1/projects', [
            'street' => 'Rua das Flores', 'city' => 'Lisbon', 'country' => 'Portugal',
        ])->assertCreated()->json('data.project_id');

        $this->postJson("/api/v1/projects/{$projectId}/select-candidate", ['index' => 1])->assertOk();
        $this->postJson("/api/v1/projects/{$projectId}/generate-render")->assertStatus(500);
    }
}
