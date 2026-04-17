<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Itemised pricing: each line is a label + quantity + unit price + subtotal.
 * Totals are margin/discount/VAT aware and expressed in the configured currency.
 */
final class PricingBreakdown
{
    /** @param list<array{label: string, qty: float, unit: string, unit_price: float, subtotal: float}> $lineItems */
    public function __construct(
        public readonly string $currency,
        public readonly array  $lineItems,
        public readonly float  $equipmentCost,
        public readonly float  $laborCost,
        public readonly float  $subtotal,
        public readonly float  $margin,
        public readonly float  $discount,
        public readonly float  $netBeforeVat,
        public readonly float  $vat,
        public readonly float  $total,
        public readonly float  $pricePerKwp,
        public readonly float  $pricePerPanel,
        public readonly int    $totalPanels,
        public readonly float  $totalKwp,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'currency'         => $this->currency,
            'line_items'       => $this->lineItems,
            'equipment_cost'   => round($this->equipmentCost, 2),
            'labor_cost'       => round($this->laborCost, 2),
            'subtotal'         => round($this->subtotal, 2),
            'margin'           => round($this->margin, 2),
            'discount'         => round($this->discount, 2),
            'net_before_vat'   => round($this->netBeforeVat, 2),
            'vat'              => round($this->vat, 2),
            'total'            => round($this->total, 2),
            'price_per_kwp'    => round($this->pricePerKwp, 2),
            'price_per_panel'  => round($this->pricePerPanel, 2),
            'total_panels'     => $this->totalPanels,
            'total_kwp'        => round($this->totalKwp, 4),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var list<array{label: string, qty: float, unit: string, unit_price: float, subtotal: float}> $items */
        $items = [];
        foreach ((array)($data['line_items'] ?? []) as $row) {
            $items[] = [
                'label'      => (string)($row['label'] ?? ''),
                'qty'        => (float)($row['qty'] ?? 0),
                'unit'       => (string)($row['unit'] ?? ''),
                'unit_price' => (float)($row['unit_price'] ?? 0),
                'subtotal'   => (float)($row['subtotal'] ?? 0),
            ];
        }
        return new self(
            currency:       (string)($data['currency'] ?? 'EUR'),
            lineItems:      $items,
            equipmentCost:  (float)($data['equipment_cost'] ?? 0),
            laborCost:      (float)($data['labor_cost'] ?? 0),
            subtotal:       (float)($data['subtotal'] ?? 0),
            margin:         (float)($data['margin'] ?? 0),
            discount:       (float)($data['discount'] ?? 0),
            netBeforeVat:   (float)($data['net_before_vat'] ?? 0),
            vat:            (float)($data['vat'] ?? 0),
            total:          (float)($data['total'] ?? 0),
            pricePerKwp:    (float)($data['price_per_kwp'] ?? 0),
            pricePerPanel:  (float)($data['price_per_panel'] ?? 0),
            totalPanels:    (int)($data['total_panels'] ?? 0),
            totalKwp:       (float)($data['total_kwp'] ?? 0),
        );
    }
}
