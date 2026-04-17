<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_index_seeds_defaults_from_config(): void
    {
        $response = $this->getJson('/api/v1/settings')->assertOk();
        $response
            ->assertJsonPath('data.panel.model', 'Generic 420W Monocrystalline')
            ->assertJsonPath('data.pricing.currency', 'EUR')
            ->assertJsonPath('data.branding.company_name', 'Helios Rooftops');

        Storage::disk('local')->assertExists('config/pricing_defaults.txt');
        Storage::disk('local')->assertExists('config/pricing_defaults.json');
    }

    public function test_update_merges_values_and_persists_to_disk(): void
    {
        $this->postJson('/api/v1/settings', [
            'section' => 'pricing',
            'values'  => ['panel_price' => 199.99, 'discount_pct' => 0.05],
        ])
            ->assertOk()
            ->assertJsonPath('data.panel_price', 199.99)
            ->assertJsonPath('data.discount_pct', 0.05)
            ->assertJsonPath('data.currency', 'EUR');

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.pricing.panel_price', 199.99);
    }

    public function test_unknown_section_is_rejected(): void
    {
        $this->postJson('/api/v1/settings', ['section' => 'bogus', 'values' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['section']);
    }
}
