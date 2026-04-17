<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\PricingBreakdown;
use App\DTOs\RoofLayout;
use App\DTOs\SavingsForecast;
use App\Repositories\ProjectFileRepository;

/**
 * Builds the year-by-year savings forecast from the stored layout, pricing and
 * editable savings assumptions.
 *
 * Annual production decays 0.5%/yr, grid tariff grows at annual_inflation_pct.
 */
final class SavingsService
{
    private const PANEL_DEGRADATION_PCT = 0.005;

    public function __construct(
        private readonly ProjectFileRepository $projects,
        private readonly ConfigFileService $config,
    ) {
    }

    public function compute(RoofLayout $layout, PricingBreakdown $pricing): SavingsForecast
    {
        $cfg = $this->config->section('savings');

        $horizon        = (int)   ($cfg['horizon_years']          ?? 25);
        $gridTariff     = (float) ($cfg['grid_tariff_per_kwh']    ?? 0.32);
        $exportTariff   = (float) ($cfg['export_tariff_per_kwh'] ?? 0.08);
        $selfRatio      = min(1.0, max(0.0, (float)($cfg['self_consumption_ratio'] ?? 0.55)));
        $inflation      = (float) ($cfg['annual_inflation_pct']   ?? 0.03);

        $yearly = [];
        $cumulative = 0.0;
        $firstYearSavings = 0.0;
        $payback = 0.0;
        $paybackFound = false;

        for ($y = 1; $y <= $horizon; $y++) {
            $degradation = (1.0 - self::PANEL_DEGRADATION_PCT) ** ($y - 1);
            $production  = $layout->annualKwh * $degradation;
            $selfKwh     = $production * $selfRatio;
            $exportKwh   = $production - $selfKwh;

            $gridPriceYear   = $gridTariff   * ((1.0 + $inflation) ** ($y - 1));
            $exportPriceYear = $exportTariff * ((1.0 + $inflation) ** ($y - 1));

            $savings = $selfKwh * $gridPriceYear + $exportKwh * $exportPriceYear;
            $prevCumulative = $cumulative;
            $cumulative += $savings;

            if ($y === 1) {
                $firstYearSavings = $savings;
            }
            if (!$paybackFound && $cumulative >= $pricing->total && $pricing->total > 0) {
                $needed = $pricing->total - $prevCumulative;
                $payback = ($y - 1) + ($savings > 0 ? $needed / $savings : 0);
                $paybackFound = true;
            }

            $yearly[] = [
                'year'               => $y,
                'production_kwh'     => round($production, 1),
                'self_consumed_kwh'  => round($selfKwh, 1),
                'exported_kwh'       => round($exportKwh, 1),
                'annual_savings'     => round($savings, 2),
                'cumulative_savings' => round($cumulative, 2),
            ];
        }
        if (!$paybackFound) {
            $payback = (float) $horizon;
        }

        return new SavingsForecast(
            currency:              $pricing->currency,
            horizonYears:          $horizon,
            gridTariffPerKwh:      $gridTariff,
            exportTariffPerKwh:    $exportTariff,
            selfConsumptionRatio:  $selfRatio,
            annualInflationPct:    $inflation,
            firstYearSavings:      $firstYearSavings,
            lifetimeSavings:       $cumulative,
            paybackYears:          $payback,
            yearly:                $yearly,
        );
    }
}
