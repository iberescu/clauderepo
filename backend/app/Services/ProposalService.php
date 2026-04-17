<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\PricingBreakdown;
use App\DTOs\ProposalSummary;
use App\DTOs\SavingsForecast;
use App\Repositories\ProjectFileRepository;
use App\Support\MinimalPdf;
use App\Support\TextKv;
use DateTimeImmutable;
use Illuminate\Contracts\View\Factory as ViewFactory;
use RuntimeException;

/**
 * Composes the end-of-funnel proposal: bundles layout + pricing + savings +
 * branding, renders a branded HTML page, writes a standalone PDF and stores
 * a reproducible .txt/.json twin under proposal/.
 *
 * AI is never consulted here — all content is derived deterministically from
 * the stored files produced by earlier phases.
 */
final class ProposalService
{
    public function __construct(
        private readonly ProjectFileRepository $projects,
        private readonly ConfigFileService $config,
        private readonly PricingService $pricing,
        private readonly SavingsService $savings,
        private readonly StatusFileService $status,
        private readonly ViewFactory $views,
    ) {
    }

    public function generate(string $projectId): ProposalSummary
    {
        $layout   = $this->projects->readLayout($projectId);
        $building = $this->projects->readBuildingInsights($projectId);
        $analysis = $this->projects->readSolarAnalysis($projectId);
        $candidate = $this->projects->readSelectedCandidate($projectId);

        if ($layout === null || $building === null || $analysis === null || $candidate === null) {
            throw new RuntimeException(
                "Proposal requires layout, building insights, solar analysis and a selected candidate."
            );
        }

        $pricingBreakdown = $this->loadOrComputePricing($projectId, $layout);
        $savingsForecast  = $this->savings->compute($layout, $pricingBreakdown);
        $this->persistSavings($projectId, $savingsForecast);

        $branding = $this->config->section('branding');
        $panelCfg = $this->config->section('panel');

        $now = new DateTimeImmutable('now');
        $expiryDays = (int)($branding['proposal_expiry_days'] ?? 30);
        $validUntil = $now->modify("+{$expiryDays} days");

        $renderRelativePath = $this->resolveRenderPath($projectId);

        $summary = new ProposalSummary(
            projectId:       $projectId,
            generatedAt:     $now->format(DATE_ATOM),
            validUntil:      $validUntil->format('Y-m-d'),
            customerAddress: $candidate->formattedAddress,
            branding:        $branding,
            panelSpec:       $panelCfg,
            building:        $building,
            analysis:        $analysis,
            layout:          $layout,
            pricing:         $pricingBreakdown,
            savings:         $savingsForecast,
            renderImagePath: $renderRelativePath,
        );

        $this->persistSummary($projectId, $summary);
        $html = $this->renderHtml($summary);
        $this->projects->writeText($projectId, 'proposal/proposal.html', $html);
        $this->projects->writeBinary($projectId, 'proposal/proposal.pdf', $this->buildPdf($summary));

        $this->status->progress($projectId, sprintf(
            'proposal: %d panels, %.2f kWp, total=%.2f %s, payback=%.2fy',
            $summary->layout->totalPanels, $summary->layout->totalKwp,
            $summary->pricing->total, $summary->pricing->currency,
            $summary->savings->paybackYears,
        ));
        $this->status->setStatus($projectId, StatusFileService::STATUS_PROPOSAL_READY);

        return $summary;
    }

    public function renderHtml(ProposalSummary $summary): string
    {
        $renderDataUri = null;
        if ($summary->renderImagePath !== null) {
            $bytes = $this->projects->readBinary($summary->projectId, $summary->renderImagePath);
            if ($bytes !== null) {
                $renderDataUri = 'data:image/png;base64,'.base64_encode($bytes);
            }
        }

        return (string) $this->views->make('proposal.proposal', [
            'summary' => $summary,
            'renderDataUri' => $renderDataUri,
        ])->render();
    }

    private function loadOrComputePricing(string $projectId, \App\DTOs\RoofLayout $layout): PricingBreakdown
    {
        $existing = $this->projects->readJson($projectId, 'pricing/pricing_breakdown.json');
        if (is_array($existing)) {
            return PricingBreakdown::fromArray($existing);
        }
        return $this->pricing->generate($projectId);
    }

    private function persistSavings(string $projectId, SavingsForecast $forecast): void
    {
        $this->projects->writeJson($projectId, 'pricing/savings_forecast.json', $forecast->toArray());

        $rows = ['# savings forecast ('.$forecast->currency.')', ''];
        $rows[] = sprintf('  horizon_years          : %d', $forecast->horizonYears);
        $rows[] = sprintf('  grid_tariff_per_kwh    : %.4f', $forecast->gridTariffPerKwh);
        $rows[] = sprintf('  export_tariff_per_kwh  : %.4f', $forecast->exportTariffPerKwh);
        $rows[] = sprintf('  self_consumption_ratio : %.2f', $forecast->selfConsumptionRatio);
        $rows[] = sprintf('  annual_inflation_pct   : %.3f', $forecast->annualInflationPct);
        $rows[] = sprintf('  first_year_savings     : %.2f', $forecast->firstYearSavings);
        $rows[] = sprintf('  lifetime_savings       : %.2f', $forecast->lifetimeSavings);
        $rows[] = sprintf('  payback_years          : %.2f', $forecast->paybackYears);
        $rows[] = '';
        $rows[] = sprintf('  %-4s %12s %12s %12s %14s %16s', 'yr', 'kWh', 'self', 'export', 'savings', 'cumulative');
        foreach ($forecast->yearly as $row) {
            $rows[] = sprintf(
                '  %-4d %12.1f %12.1f %12.1f %14.2f %16.2f',
                $row['year'],
                $row['production_kwh'],
                $row['self_consumed_kwh'],
                $row['exported_kwh'],
                $row['annual_savings'],
                $row['cumulative_savings'],
            );
        }
        $this->projects->writeText($projectId, 'pricing/savings_forecast.txt', implode("\n", $rows)."\n");
    }

    private function persistSummary(string $projectId, ProposalSummary $s): void
    {
        $this->projects->writeJson($projectId, 'proposal/proposal_summary.json', $s->toArray());
        $this->projects->writeText($projectId, 'proposal/proposal_summary.txt', TextKv::render([
            'project_id'       => $s->projectId,
            'generated_at'     => $s->generatedAt,
            'valid_until'      => $s->validUntil,
            'customer_address' => $s->customerAddress,
            'company_name'     => $s->branding['company_name'] ?? '',
            'total_panels'     => $s->layout->totalPanels,
            'total_kwp'        => round($s->layout->totalKwp, 3),
            'annual_kwh'       => round($s->layout->annualKwh, 1),
            'total'            => round($s->pricing->total, 2),
            'currency'         => $s->pricing->currency,
            'first_year_savings' => round($s->savings->firstYearSavings, 2),
            'lifetime_savings'   => round($s->savings->lifetimeSavings, 2),
            'payback_years'      => round($s->savings->paybackYears, 2),
            'render_image'       => $s->renderImagePath,
        ]));
    }

    private function resolveRenderPath(string $projectId): ?string
    {
        foreach (['render/roof_render.png', 'render/roof_overlay.png', 'render/roof_base.png'] as $rel) {
            if ($this->projects->readBinary($projectId, $rel) !== null) {
                return $rel;
            }
        }
        return null;
    }

    private function buildPdf(ProposalSummary $s): string
    {
        $pdf = new MinimalPdf();
        $currency = $s->pricing->currency;
        $company  = (string)($s->branding['company_name'] ?? 'Solar Proposal');
        $tagline  = (string)($s->branding['tagline'] ?? '');

        $pdf->title($company)
            ->paragraph($tagline === '' ? 'Rooftop solar proposal' : $tagline, 11)
            ->kv('Proposal for', $s->customerAddress)
            ->kv('Generated', substr($s->generatedAt, 0, 19))
            ->kv('Valid until', $s->validUntil)
            ->kv('Project ID', $s->projectId)
            ->spacer(4);

        $pdf->heading('System at a glance')
            ->kv('Panels', sprintf('%d x %s', $s->layout->totalPanels, (string)($s->panelSpec['model'] ?? 'module')))
            ->kv('Installed capacity', sprintf('%.2f kWp', $s->layout->totalKwp))
            ->kv('Expected annual production', sprintf('%.0f kWh', $s->layout->annualKwh))
            ->kv('Usable roof area', sprintf('%.1f m2', $s->analysis->usableRoofAreaM2))
            ->kv('Sunshine hours / year', sprintf('%.0f h', $s->analysis->averageSunshineHoursPerYear))
            ->kv('Average pitch', sprintf('%.1f deg', $s->analysis->averagePitchDeg));

        $pdf->heading('Investment');
        $rows = [['Item', 'Qty', 'Unit', 'Unit price', 'Subtotal']];
        foreach ($s->pricing->lineItems as $li) {
            $rows[] = [
                (string) $li['label'],
                number_format((float) $li['qty'], 2),
                (string) $li['unit'],
                number_format((float) $li['unit_price'], 2),
                number_format((float) $li['subtotal'], 2),
            ];
        }
        $pdf->table($rows);

        $pdf->kv('Equipment subtotal', $this->money($s->pricing->equipmentCost, $currency))
            ->kv('Labour subtotal',    $this->money($s->pricing->laborCost, $currency))
            ->kv('Subtotal',           $this->money($s->pricing->subtotal, $currency))
            ->kv('Margin',             $this->money($s->pricing->margin, $currency))
            ->kv('Discount',           '-'.$this->money($s->pricing->discount, $currency))
            ->kv('Net before VAT',     $this->money($s->pricing->netBeforeVat, $currency))
            ->kv('VAT',                $this->money($s->pricing->vat, $currency))
            ->kv('TOTAL',              $this->money($s->pricing->total, $currency))
            ->kv('Price / kWp',        $this->money($s->pricing->pricePerKwp, $currency))
            ->kv('Price / panel',      $this->money($s->pricing->pricePerPanel, $currency));

        $pdf->heading('Savings over '.$s->savings->horizonYears.' years')
            ->kv('Grid tariff', sprintf('%.4f %s / kWh', $s->savings->gridTariffPerKwh, $currency))
            ->kv('Export tariff', sprintf('%.4f %s / kWh', $s->savings->exportTariffPerKwh, $currency))
            ->kv('Self-consumption ratio', sprintf('%.0f %%', $s->savings->selfConsumptionRatio * 100))
            ->kv('Annual tariff inflation', sprintf('%.1f %%', $s->savings->annualInflationPct * 100))
            ->kv('First-year savings', $this->money($s->savings->firstYearSavings, $currency))
            ->kv('Lifetime savings',   $this->money($s->savings->lifetimeSavings, $currency))
            ->kv('Payback',            sprintf('%.2f years', $s->savings->paybackYears));

        $yrRows = [['Year', 'Production kWh', 'Savings', 'Cumulative']];
        foreach (array_slice($s->savings->yearly, 0, 10) as $row) {
            $yrRows[] = [
                (string) $row['year'],
                number_format((float) $row['production_kwh'], 0),
                number_format((float) $row['annual_savings'], 2),
                number_format((float) $row['cumulative_savings'], 2),
            ];
        }
        $pdf->table($yrRows);

        $pdf->heading('Contact')
            ->kv('Company', $company)
            ->kv('Email',   (string)($s->branding['contact_email'] ?? ''))
            ->kv('Phone',   (string)($s->branding['contact_phone'] ?? ''))
            ->kv('Website', (string)($s->branding['website'] ?? ''))
            ->spacer(6)
            ->paragraph((string)($s->branding['disclaimer'] ?? ''), 9.0);

        return $pdf->build();
    }

    private function money(float $amount, string $currency): string
    {
        return number_format($amount, 2).' '.$currency;
    }
}
