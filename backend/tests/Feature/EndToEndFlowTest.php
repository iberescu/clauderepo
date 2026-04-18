<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 6 end-to-end test.
 *
 * Walks a single project through every HTTP endpoint in the order a sales
 * user drives the UI: create → pick candidate → analyse → layout → render →
 * pricing → proposal. Asserts that (a) each status transition lands, (b) every
 * artefact listed in the spec ends up on disk, and (c) the HTML + PDF
 * endpoints serve the generated proposal.
 */
class EndToEndFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config()->set('solar.fake_providers', true);
    }

    public function test_full_pipeline_from_address_to_signed_proposal(): void
    {
        $disk = Storage::disk('local');

        // 1. Create project + resolve street → 12 candidates.
        $create = $this->postJson('/api/v1/projects', [
            'street'  => 'Rua das Flores',
            'city'    => 'Lisbon',
            'country' => 'Portugal',
        ])->assertCreated();

        $projectId = $create->json('data.project_id');
        $this->assertIsString($projectId);
        $this->assertCount(12, $create->json('data.candidates'));
        $this->assertStatus($projectId, 'candidates_ready');

        // 2. Pick a candidate.
        $this->postJson("/api/v1/projects/{$projectId}/select-candidate", ['index' => 2])
            ->assertOk()
            ->assertJsonPath('data.index', 2);
        $this->assertStatus($projectId, 'candidate_selected');

        // 3. Solar analysis.
        $this->postJson("/api/v1/projects/{$projectId}/analyze-building")
            ->assertOk()
            ->assertJsonStructure(['data' => ['analysis' => [
                'usable_roof_area_m2', 'average_pitch_deg', 'usable_segments',
            ]]]);
        $this->assertStatus($projectId, 'analysis_ready');

        // 4. Deterministic panel layout.
        $layout = $this->postJson("/api/v1/projects/{$projectId}/generate-layout")
            ->assertOk()
            ->assertJsonStructure(['data' => ['layout' => ['total_panels', 'total_kwp', 'annual_kwh']]])
            ->json('data.layout');
        $this->assertGreaterThan(0, $layout['total_panels']);
        $this->assertGreaterThan(0, $layout['annual_kwh']);
        $this->assertStatus($projectId, 'layout_ready');

        // 5. Rendering pipeline — synthetic + real-aerial + satellite composites with 3D Gemini passes.
        $this->postJson("/api/v1/projects/{$projectId}/generate-render")
            ->assertOk()
            ->assertJsonStructure(['data' => ['images' => [
                'roof_base', 'roof_overlay', 'roof_render', 'roof_render_3d',
                'real_aerial_overlay', 'real_aerial_render_3d',
                'static_map_base', 'static_map_overlay', 'static_map_render_3d',
            ]]]);
        $this->assertStatus($projectId, 'render_ready');

        $renderPngs = [
            'roof_base.png', 'roof_overlay.png', 'roof_render.png', 'roof_render_3d.png',
            'real_aerial_overlay.png', 'real_aerial_render_3d.png',
            'static_map_base.png', 'static_map_overlay.png', 'static_map_render_3d.png',
        ];
        foreach ($renderPngs as $name) {
            $bytes = (string) $disk->get("projects/{$projectId}/render/{$name}");
            $this->assertSame("\x89PNG\r\n\x1a\n", substr($bytes, 0, 8), "{$name} is not a valid PNG");
        }

        // 6. Pricing breakdown.
        $pricing = $this->postJson("/api/v1/projects/{$projectId}/generate-pricing")
            ->assertOk()
            ->assertJsonStructure(['data' => ['pricing' => ['total', 'line_items', 'currency']]])
            ->json('data.pricing');
        $this->assertGreaterThan(0, $pricing['total']);
        $this->assertNotEmpty($pricing['line_items']);
        $this->assertStatus($projectId, 'pricing_ready');

        // 7. Proposal (savings + HTML + PDF).
        $proposal = $this->postJson("/api/v1/projects/{$projectId}/generate-proposal")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary' => ['savings' => ['payback_years', 'first_year_savings', 'lifetime_savings']],
                    'links'   => ['html', 'pdf'],
                ],
            ])
            ->json('data');

        $this->assertStatus($projectId, 'proposal_ready');
        $this->assertGreaterThan(0, $proposal['summary']['savings']['lifetime_savings']);

        // 8. Every artefact landed on disk, in the spec's folder tree.
        $expected = [
            'input/request.txt',
            'input/normalized_query.txt',
            'candidates/candidates.txt',
            'building/selected_building.txt',
            'building/building_summary.txt',
            'building/roof_segments.txt',
            'building/building_insights.json',
            'solar/solar_analysis.txt',
            'solar/images/rgb.png',
            'solar/images/mask.png',
            'solar/images/flux.png',
            'solar/images/aerial_geo.json',
            'layout/panel_coordinates.txt',
            'layout/layout_summary.txt',
            'render/roof_base.png',
            'render/roof_overlay.png',
            'render/roof_render.png',
            'render/roof_render_3d.png',
            'render/real_aerial_overlay.png',
            'render/real_aerial_render_3d.png',
            'render/static_map_base.png',
            'render/static_map_overlay.png',
            'render/static_map_render_3d.png',
            'render/static_map_geo.json',
            'render/gemini_prompt.txt',
            'render/gemini_prompt_3d.txt',
            'render/render_notes.txt',
            'pricing/pricing_breakdown.txt',
            'pricing/pricing_breakdown.json',
            'pricing/savings_forecast.json',
            'proposal/proposal_summary.json',
            'proposal/proposal.html',
            'proposal/proposal.pdf',
        ];
        foreach ($expected as $relative) {
            $this->assertTrue(
                $disk->exists("projects/{$projectId}/{$relative}"),
                "Expected artefact missing: {$relative}"
            );
        }

        // 9. Imagery endpoints stream PNG bytes.
        foreach (['rgb', 'mask', 'flux'] as $name) {
            $img = $this->get("/api/v1/projects/{$projectId}/solar/images/{$name}.png")->assertOk();
            $img->assertHeader('Content-Type', 'image/png');
            $this->assertSame("\x89PNG\r\n\x1a\n", substr((string) $img->getContent(), 0, 8));
        }
        foreach (['roof_render_3d.png', 'real_aerial_render_3d.png', 'static_map_render_3d.png'] as $rname) {
            $resp = $this->get("/api/v1/projects/{$projectId}/render/images/{$rname}")->assertOk();
            $resp->assertHeader('Content-Type', 'image/png');
        }

        // 10. HTML + PDF endpoints stream the generated files, including the new imagery.
        $html = $this->get("/api/v1/projects/{$projectId}/proposal/html")->assertOk();
        $htmlBody = (string) $html->getContent();
        $this->assertStringContainsString('Rooftop solar proposal', $htmlBody);
        $this->assertStringContainsString('data:image/png;base64,', $htmlBody);
        $this->assertStringContainsString('Roof imagery from Google Solar', $htmlBody);
        $this->assertStringContainsString('Google Solar RGB', $htmlBody);
        $this->assertStringContainsString('Google Maps satellite tile', $htmlBody);

        $pdf = $this->get("/api/v1/projects/{$projectId}/proposal/pdf")->assertOk();
        $pdf->assertHeader('Content-Type', 'application/pdf');
        $pdfBytes = (string) $pdf->getContent();
        $this->assertSame('%PDF-1.4', substr($pdfBytes, 0, 8));
        $this->assertStringContainsString('%%EOF', $pdfBytes);
        // Verify image XObjects were embedded in the PDF.
        $this->assertStringContainsString('/Subtype /Image', $pdfBytes);
        $this->assertStringContainsString('/Filter /DCTDecode', $pdfBytes);

        // 11. Project summary endpoint reflects the full pipeline state.
        $this->getJson("/api/v1/projects/{$projectId}")
            ->assertOk()
            ->assertJsonPath('data.project_id', $projectId)
            ->assertJsonPath('data.request.street', 'Rua das Flores');

        $this->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonFragment([$projectId]);
    }

    private function assertStatus(string $projectId, string $expected): void
    {
        $this->getJson("/api/v1/projects/{$projectId}/status")
            ->assertOk()
            ->assertJsonPath('data.current.status', $expected);
    }
}
