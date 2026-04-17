<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\PricingBreakdown;
use App\DTOs\RoofLayout;
use App\Repositories\ProjectFileRepository;
use App\Support\TextKv;
use RuntimeException;

/**
 * Deterministic pricing engine.
 *
 * Reads the editable pricing config (ConfigFileService) and the stored roof
 * layout, emits a line-item breakdown including equipment, inverter, mounting,
 * labour, BoS, margin, discount and VAT.
 *
 * All numbers are reproducible — no RNG, no external calls.
 */
final class PricingService
{
    public function __construct(
        private readonly ProjectFileRepository $projects,
        private readonly ConfigFileService $config,
        private readonly StatusFileService $status,
    ) {
    }

    public function generate(string $projectId): PricingBreakdown
    {
        $layout = $this->projects->readLayout($projectId);
        if ($layout === null) {
            throw new RuntimeException("Layout not available for project {$projectId}");
        }

        $pricing = $this->config->section('pricing');
        $panelCfg = $this->config->section('panel');

        $breakdown = $this->compute($layout, $pricing, $panelCfg);
        $this->persist($projectId, $breakdown);
        $this->status->progress($projectId, sprintf(
            'pricing: total=%.2f %s for %d panels (%.2f kWp)',
            $breakdown->total, $breakdown->currency,
            $breakdown->totalPanels, $breakdown->totalKwp,
        ));
        $this->status->setStatus($projectId, StatusFileService::STATUS_PRICING_READY);

        return $breakdown;
    }

    /**
     * @param array<string, mixed> $pricing
     * @param array<string, mixed> $panelCfg
     */
    public function compute(RoofLayout $layout, array $pricing, array $panelCfg): PricingBreakdown
    {
        $currency   = (string)($pricing['currency']           ?? 'EUR');
        $panelPrice = (float) ($pricing['panel_price']        ?? 180.0);
        $invPerKwp  = (float) ($pricing['inverter_per_kwp']   ?? 180.0);
        $mount      = (float) ($pricing['mounting_per_panel'] ?? 55.0);
        $labor      = (float) ($pricing['labor_per_panel']    ?? 90.0);
        $fixedBos   = (float) ($pricing['fixed_bos']          ?? 450.0);
        $difficulty = (float) ($pricing['roof_difficulty']    ?? 1.0);
        $marginPct  = (float) ($pricing['margin_pct']         ?? 0.18);
        $discPct    = (float) ($pricing['discount_pct']       ?? 0.0);
        $vatPct     = (float) ($pricing['vat_pct']            ?? 0.21);
        $panelModel = (string)($panelCfg['model']             ?? 'Solar panel');

        $panels = $layout->totalPanels;
        $kwp    = $layout->totalKwp;

        $panelTotal  = $panels * $panelPrice;
        $invTotal    = $kwp * $invPerKwp;
        $mountTotal  = $panels * $mount * $difficulty;
        $laborTotal  = $panels * $labor * $difficulty;

        $lineItems = [
            $this->line("{$panelModel} modules",  $panels,  'panels',  $panelPrice),
            $this->line('Inverter(s) + DC wiring', $kwp,     'kWp',     $invPerKwp),
            $this->line('Mounting & structure',    $panels,  'panels',  $mount * $difficulty),
            $this->line('Installation labour',     $panels,  'panels',  $labor * $difficulty),
            $this->line('Balance of system',       1,        'lot',     $fixedBos),
        ];

        $equipmentCost = $panelTotal + $invTotal + $mountTotal + $fixedBos;
        $laborCost     = $laborTotal;
        $subtotal      = $equipmentCost + $laborCost;

        $margin       = $subtotal * $marginPct;
        $discount     = ($subtotal + $margin) * $discPct;
        $netBeforeVat = max(0.0, $subtotal + $margin - $discount);
        $vat          = $netBeforeVat * $vatPct;
        $total        = $netBeforeVat + $vat;

        return new PricingBreakdown(
            currency:       $currency,
            lineItems:      $lineItems,
            equipmentCost:  $equipmentCost,
            laborCost:      $laborCost,
            subtotal:       $subtotal,
            margin:         $margin,
            discount:       $discount,
            netBeforeVat:   $netBeforeVat,
            vat:            $vat,
            total:          $total,
            pricePerKwp:    $kwp > 0.0 ? $total / $kwp : 0.0,
            pricePerPanel:  $panels > 0 ? $total / $panels : 0.0,
            totalPanels:    $panels,
            totalKwp:       $kwp,
        );
    }

    /** @return array{label: string, qty: float, unit: string, unit_price: float, subtotal: float} */
    private function line(string $label, float|int $qty, string $unit, float $unitPrice): array
    {
        $q = (float) $qty;
        return [
            'label'      => $label,
            'qty'        => $q,
            'unit'       => $unit,
            'unit_price' => $unitPrice,
            'subtotal'   => round($q * $unitPrice, 2),
        ];
    }

    private function persist(string $projectId, PricingBreakdown $b): void
    {
        $this->projects->writeJson($projectId, 'pricing/pricing_breakdown.json', $b->toArray());

        $lines = ['# pricing breakdown ('.$b->currency.')', ''];
        foreach ($b->lineItems as $li) {
            $lines[] = sprintf(
                '  %-32s %8.2f %-8s @ %10.2f = %12.2f',
                $li['label'], $li['qty'], $li['unit'], $li['unit_price'], $li['subtotal'],
            );
        }
        $lines[] = '';
        $lines[] = sprintf('  %-32s %42.2f', 'Equipment subtotal', $b->equipmentCost);
        $lines[] = sprintf('  %-32s %42.2f', 'Labour subtotal',    $b->laborCost);
        $lines[] = sprintf('  %-32s %42.2f', 'Subtotal',           $b->subtotal);
        $lines[] = sprintf('  %-32s %42.2f', 'Margin',             $b->margin);
        $lines[] = sprintf('  %-32s %42.2f', 'Discount',           -$b->discount);
        $lines[] = sprintf('  %-32s %42.2f', 'Net before VAT',     $b->netBeforeVat);
        $lines[] = sprintf('  %-32s %42.2f', 'VAT',                $b->vat);
        $lines[] = sprintf('  %-32s %42.2f', 'TOTAL',              $b->total);
        $lines[] = '';
        $lines[] = sprintf('  price / kWp   : %.2f %s', $b->pricePerKwp,   $b->currency);
        $lines[] = sprintf('  price / panel : %.2f %s', $b->pricePerPanel, $b->currency);
        $this->projects->writeText($projectId, 'pricing/pricing_breakdown.txt', implode("\n", $lines)."\n");

        $this->projects->writeText($projectId, 'pricing/assumptions.txt', TextKv::render([
            'currency'           => $b->currency,
            'total_panels'       => $b->totalPanels,
            'total_kwp'          => $b->totalKwp,
            'total'              => round($b->total, 2),
            'price_per_kwp'      => round($b->pricePerKwp, 2),
            'generated_at'       => (new \DateTimeImmutable('now'))->format(DATE_ATOM),
        ]));
    }
}
