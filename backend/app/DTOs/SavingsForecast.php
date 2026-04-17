<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Cash-flow projection: per-year production, self-consumption vs export value,
 * cumulative savings, and payback period.
 */
final class SavingsForecast
{
    /** @param list<array{year: int, production_kwh: float, self_consumed_kwh: float, exported_kwh: float, annual_savings: float, cumulative_savings: float}> $yearly */
    public function __construct(
        public readonly string $currency,
        public readonly int    $horizonYears,
        public readonly float  $gridTariffPerKwh,
        public readonly float  $exportTariffPerKwh,
        public readonly float  $selfConsumptionRatio,
        public readonly float  $annualInflationPct,
        public readonly float  $firstYearSavings,
        public readonly float  $lifetimeSavings,
        public readonly float  $paybackYears,
        public readonly array  $yearly,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'currency'                => $this->currency,
            'horizon_years'           => $this->horizonYears,
            'grid_tariff_per_kwh'     => $this->gridTariffPerKwh,
            'export_tariff_per_kwh'   => $this->exportTariffPerKwh,
            'self_consumption_ratio'  => $this->selfConsumptionRatio,
            'annual_inflation_pct'    => $this->annualInflationPct,
            'first_year_savings'      => round($this->firstYearSavings, 2),
            'lifetime_savings'        => round($this->lifetimeSavings, 2),
            'payback_years'           => round($this->paybackYears, 2),
            'yearly'                  => $this->yearly,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var list<array{year: int, production_kwh: float, self_consumed_kwh: float, exported_kwh: float, annual_savings: float, cumulative_savings: float}> $yearly */
        $yearly = [];
        foreach ((array)($data['yearly'] ?? []) as $row) {
            $yearly[] = [
                'year'               => (int)($row['year'] ?? 0),
                'production_kwh'     => (float)($row['production_kwh'] ?? 0),
                'self_consumed_kwh'  => (float)($row['self_consumed_kwh'] ?? 0),
                'exported_kwh'       => (float)($row['exported_kwh'] ?? 0),
                'annual_savings'     => (float)($row['annual_savings'] ?? 0),
                'cumulative_savings' => (float)($row['cumulative_savings'] ?? 0),
            ];
        }
        return new self(
            currency:              (string)($data['currency'] ?? 'EUR'),
            horizonYears:          (int)($data['horizon_years'] ?? 25),
            gridTariffPerKwh:      (float)($data['grid_tariff_per_kwh'] ?? 0),
            exportTariffPerKwh:    (float)($data['export_tariff_per_kwh'] ?? 0),
            selfConsumptionRatio:  (float)($data['self_consumption_ratio'] ?? 0),
            annualInflationPct:    (float)($data['annual_inflation_pct'] ?? 0),
            firstYearSavings:      (float)($data['first_year_savings'] ?? 0),
            lifetimeSavings:       (float)($data['lifetime_savings'] ?? 0),
            paybackYears:          (float)($data['payback_years'] ?? 0),
            yearly:                $yearly,
        );
    }
}
