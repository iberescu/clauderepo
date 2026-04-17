<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProposalFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config()->set('solar.fake_providers', true);
    }

    private function buildUpToRender(): string
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

    public function test_generate_proposal_writes_html_and_pdf(): void
    {
        $projectId = $this->buildUpToRender();
        $this->postJson("/api/v1/projects/{$projectId}/generate-pricing")->assertOk();

        $response = $this->postJson("/api/v1/projects/{$projectId}/generate-proposal")->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'project_id',
                'summary' => ['project_id','generated_at','valid_until','customer_address','pricing','savings','layout'],
                'links' => ['html', 'pdf'],
            ],
        ]);

        $disk = Storage::disk('local');
        $base = "projects/{$projectId}/proposal";
        foreach (['proposal_summary.json', 'proposal_summary.txt', 'proposal.html', 'proposal.pdf'] as $name) {
            $this->assertTrue($disk->exists("{$base}/{$name}"), "Missing {$name}");
        }

        // PDF magic + EOF marker
        $pdf = (string) $disk->get("{$base}/proposal.pdf");
        $this->assertSame('%PDF-1.4', substr($pdf, 0, 8));
        $this->assertStringContainsString('%%EOF', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));

        // HTML contains the render image as a data URI
        $html = (string) $disk->get("{$base}/proposal.html");
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringContainsString('Rooftop solar proposal', $html);

        // Savings forecast persisted alongside pricing
        $this->assertTrue($disk->exists("projects/{$projectId}/pricing/savings_forecast.json"));
        $this->assertTrue($disk->exists("projects/{$projectId}/pricing/savings_forecast.txt"));

        $this->getJson("/api/v1/projects/{$projectId}/status")
            ->assertJsonPath('data.current.status', 'proposal_ready');
    }

    public function test_proposal_generation_computes_pricing_if_missing(): void
    {
        // Skip pricing step — ProposalService must run it on demand.
        $projectId = $this->buildUpToRender();

        $this->postJson("/api/v1/projects/{$projectId}/generate-proposal")->assertOk();

        $disk = Storage::disk('local');
        $this->assertTrue($disk->exists("projects/{$projectId}/pricing/pricing_breakdown.json"));
        $this->assertTrue($disk->exists("projects/{$projectId}/proposal/proposal.pdf"));
    }

    public function test_html_and_pdf_endpoints_stream_content(): void
    {
        $projectId = $this->buildUpToRender();
        $this->postJson("/api/v1/projects/{$projectId}/generate-proposal")->assertOk();

        $html = $this->get("/api/v1/projects/{$projectId}/proposal/html");
        $html->assertOk();
        $html->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $this->assertStringContainsString('Rooftop solar proposal', (string) $html->getContent());

        $pdf = $this->get("/api/v1/projects/{$projectId}/proposal/pdf");
        $pdf->assertOk();
        $pdf->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame('%PDF-1.4', substr((string) $pdf->getContent(), 0, 8));
    }

    public function test_generate_proposal_fails_without_layout(): void
    {
        $projectId = $this->postJson('/api/v1/projects', [
            'street' => 'Rua das Flores', 'city' => 'Lisbon', 'country' => 'Portugal',
        ])->assertCreated()->json('data.project_id');

        $this->postJson("/api/v1/projects/{$projectId}/select-candidate", ['index' => 2])->assertOk();
        $this->postJson("/api/v1/projects/{$projectId}/generate-proposal")->assertStatus(500);
    }
}
