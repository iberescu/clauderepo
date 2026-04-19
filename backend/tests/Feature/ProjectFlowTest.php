<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config()->set('solar.fake_providers', true);
    }

    public function test_health_endpoint(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertJson(['status' => 'ok']);
    }

    public function test_create_project_persists_folders_and_candidates(): void
    {
        $response = $this->postJson('/api/v1/projects', [
            'street'  => 'Rua das Flores',
            'city'    => 'Lisbon',
            'country' => 'Portugal',
        ])->assertCreated();

        $projectId = $response->json('data.project_id');
        $this->assertIsString($projectId);
        $this->assertNotEmpty($projectId);

        $disk = Storage::disk('local');
        foreach (['input','candidates','building','solar','layout','pricing','render','proposal','logs','status'] as $dir) {
            $this->assertTrue($disk->exists("projects/{$projectId}/{$dir}"), "Missing directory {$dir}");
        }

        $this->assertTrue($disk->exists("projects/{$projectId}/input/request.txt"));
        $this->assertTrue($disk->exists("projects/{$projectId}/input/normalized_query.txt"));
        $this->assertTrue($disk->exists("projects/{$projectId}/candidates/candidates.txt"));

        $candidates = $response->json('data.candidates');
        $this->assertCount(12, $candidates);
        $this->assertSame(1, $candidates[0]['index']);
    }

    public function test_validation_errors_are_returned_as_json(): void
    {
        $this->postJson('/api/v1/projects', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['street', 'city', 'country']);
    }

    public function test_select_candidate_updates_state(): void
    {
        $projectId = $this->postJson('/api/v1/projects', [
            'street' => 'Calle Mayor', 'city' => 'Madrid', 'country' => 'Spain',
        ])->assertCreated()->json('data.project_id');

        $this->postJson("/api/v1/projects/{$projectId}/select-candidate", ['index' => 3])
            ->assertOk()
            ->assertJsonPath('data.index', 3);

        $status = $this->getJson("/api/v1/projects/{$projectId}/status")->assertOk();
        $status->assertJsonPath('data.current.status', 'candidate_selected');

        $this->getJson("/api/v1/projects/{$projectId}/candidates")
            ->assertOk()
            ->assertJsonPath('selected.index', 3);
    }

    public function test_select_candidate_rejects_unknown_index(): void
    {
        $projectId = $this->postJson('/api/v1/projects', [
            'street' => 'Calle Mayor', 'city' => 'Madrid', 'country' => 'Spain',
        ])->assertCreated()->json('data.project_id');

        $this->postJson("/api/v1/projects/{$projectId}/select-candidate", ['index' => 999])
            ->assertStatus(500);
    }

    public function test_create_project_accepts_coordinates_without_address(): void
    {
        $response = $this->postJson('/api/v1/projects', [
            'lat' => 38.711046,
            'lng' => -9.139968,
        ])->assertCreated();

        $projectId = $response->json('data.project_id');
        $this->assertStringContainsString('coord-', $projectId);

        $this->assertSame(38.711046, $response->json('data.normalized_query.center_lat'));
        $this->assertSame(-9.139968, $response->json('data.normalized_query.center_lng'));
        $this->assertEquals(1.0, $response->json('data.normalized_query.confidence'));
        $this->assertCount(12, $response->json('data.candidates'));
    }

    public function test_create_project_rejects_partial_coordinates(): void
    {
        $this->postJson('/api/v1/projects', ['lat' => 38.71])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lng']);
    }

    public function test_create_project_rejects_out_of_range_coordinates(): void
    {
        $this->postJson('/api/v1/projects', ['lat' => 120.0, 'lng' => 0.0])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lat']);
    }

    public function test_show_endpoint_reflects_saved_files(): void
    {
        $projectId = $this->postJson('/api/v1/projects', [
            'street' => 'Rua das Flores', 'city' => 'Lisbon', 'country' => 'Portugal',
        ])->assertCreated()->json('data.project_id');

        $this->getJson("/api/v1/projects/{$projectId}")
            ->assertOk()
            ->assertJsonPath('data.project_id', $projectId)
            ->assertJsonPath('data.request.street', 'Rua das Flores')
            ->assertJsonPath('data.normalized_query.provider', 'fake');
    }
}
