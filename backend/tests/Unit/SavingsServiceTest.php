<?php

namespace Tests\Unit;

use App\DTOs\PanelPlacement;
use App\DTOs\PricingBreakdown;
use App\DTOs\RoofLayout;
use App\DTOs\SegmentLayout;
use App\Repositories\ProjectFileRepository;
use App\Services\ConfigFileService;
use App\Services\SavingsService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SavingsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config()->set('solar.fake_providers', true);
    }

    private function layout(int $panels, float $annualKwh): RoofLayout
    {
        $placements = [];
        for ($i = 1; $i <= $panels; $i++) {
            $placements[] = new PanelPlacement(
                index: $i, segmentIndex: 1, x: 0, y: 0, width: 1.134, height: 1.722, orientation: 'portrait',
            );
        }
        $segment = new SegmentLayout(
            segmentIndex: 1, orientation: 'portrait', rows: $panels, cols: 1,
            panels: $placements, annualKwh: $annualKwh, usedAreaM2: $panels * 1.95,
        );
        return new RoofLayout(
            segments: [$segment], panelWidthM: 1.134, panelHeightM: 1.722,
            panelWattPeak: 420, setbackM: 0.4,
            totalPanels: $panels, totalKwp: $panels * 0.420, annualKwh: $annualKwh,
            aestheticScore: 0.8,
        );
    }

    private function pricing(float $total): PricingBreakdown
    {
        return new PricingBreakdown(
            currency: 'EUR', lineItems: [],
            equipmentCost: 0, laborCost: 0, subtotal: 0, margin: 0, discount: 0,
            netBeforeVat: 0, vat: 0, total: $total,
            pricePerKwp: 0, pricePerPanel: 0, totalPanels: 0, totalKwp: 0,
        );
    }

    public function test_forecast_has_one_row_per_year_and_positive_first_year_savings(): void
    {
        $service = new SavingsService(
            new ProjectFileRepository(),
            new ConfigFileService(),
        );

        $forecast = $service->compute($this->layout(20, 9000.0), $this->pricing(15000.0));

        $this->assertSame(25, $forecast->horizonYears);
        $this->assertCount(25, $forecast->yearly);
        $this->assertGreaterThan(0, $forecast->firstYearSavings);
        $this->assertGreaterThan($forecast->firstYearSavings, $forecast->lifetimeSavings);
    }

    public function test_payback_is_reasonable_when_investment_is_low(): void
    {
        $service = new SavingsService(
            new ProjectFileRepository(),
            new ConfigFileService(),
        );

        $forecast = $service->compute($this->layout(20, 9000.0), $this->pricing(5000.0));

        $this->assertLessThan(10.0, $forecast->paybackYears);
        $this->assertGreaterThan(0.0, $forecast->paybackYears);
    }

    public function test_payback_clamps_to_horizon_when_savings_never_exceed_investment(): void
    {
        $service = new SavingsService(
            new ProjectFileRepository(),
            new ConfigFileService(),
        );

        $forecast = $service->compute($this->layout(1, 100.0), $this->pricing(1_000_000.0));

        $this->assertSame(25.0, $forecast->paybackYears);
    }

    public function test_production_decays_year_over_year(): void
    {
        $service = new SavingsService(
            new ProjectFileRepository(),
            new ConfigFileService(),
        );
        $forecast = $service->compute($this->layout(10, 5000.0), $this->pricing(10000.0));

        $y1 = $forecast->yearly[0]['production_kwh'];
        $y10 = $forecast->yearly[9]['production_kwh'];
        $this->assertGreaterThan($y10, $y1);
    }
}
