<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Solar proposal — {{ $summary->customerAddress }}</title>
  <style>
    :root {
      --brand: #1f5db9;
      --brand-dark: #15366a;
      --accent: #f59f2a;
      --text: #1a1a1a;
      --muted: #6b7280;
      --border: #e5e7eb;
      --bg-soft: #f4f6fb;
    }
    * { box-sizing: border-box; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
      color: var(--text); margin: 0; padding: 40px; background: #fff; line-height: 1.45;
    }
    .wrap { max-width: 880px; margin: 0 auto; }
    header {
      display: flex; align-items: center; justify-content: space-between;
      padding-bottom: 18px; border-bottom: 4px solid var(--brand);
    }
    header .brand { font-size: 26px; font-weight: 700; color: var(--brand-dark); }
    header .tagline { color: var(--muted); font-size: 13px; margin-top: 4px; }
    header .meta { text-align: right; font-size: 12px; color: var(--muted); }
    h1 { font-size: 22px; margin: 28px 0 6px; color: var(--brand-dark); }
    h2 {
      font-size: 15px; margin: 28px 0 8px; color: var(--brand-dark);
      padding-bottom: 4px; border-bottom: 1px solid var(--border); text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .lead { color: var(--muted); font-size: 13px; margin: 4px 0 18px; }
    .stat-grid {
      display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 10px 0 4px;
    }
    .stat {
      background: var(--bg-soft); border: 1px solid var(--border); border-radius: 8px;
      padding: 12px 14px;
    }
    .stat .label { font-size: 11px; text-transform: uppercase; color: var(--muted); letter-spacing: 0.04em; }
    .stat .value { font-size: 20px; font-weight: 700; color: var(--brand-dark); margin-top: 4px; }
    .stat .unit { font-size: 12px; color: var(--muted); margin-left: 3px; }

    .render {
      margin: 14px 0 6px; background: #eef2f8; border: 1px solid var(--border);
      border-radius: 10px; padding: 10px; text-align: center;
    }
    .render img { max-width: 100%; height: auto; border-radius: 6px; }
    .render .caption { margin-top: 8px; color: var(--muted); font-size: 12px; }

    table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
    th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid var(--border); }
    th { background: var(--bg-soft); font-weight: 600; color: var(--brand-dark); }
    td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
    tfoot td { font-weight: 600; }
    tr.total td { font-size: 14px; background: var(--brand); color: #fff; border-color: var(--brand); }

    .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
    .kv { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dotted var(--border); font-size: 13px; }
    .kv .k { color: var(--muted); }
    .kv .v { font-weight: 600; }

    footer {
      margin-top: 30px; padding-top: 14px; border-top: 1px solid var(--border);
      font-size: 11.5px; color: var(--muted);
    }
    .disclaimer { font-size: 11px; color: var(--muted); margin-top: 12px; }
  </style>
</head>
<body>
<div class="wrap">
  <header>
    <div>
      <div class="brand">{{ $summary->branding['company_name'] ?? 'Solar Proposal' }}</div>
      <div class="tagline">{{ $summary->branding['tagline'] ?? 'Rooftop photovoltaic proposal' }}</div>
    </div>
    <div class="meta">
      <div>Proposal #{{ $summary->projectId }}</div>
      <div>Generated {{ substr($summary->generatedAt, 0, 10) }}</div>
      <div>Valid until {{ $summary->validUntil }}</div>
    </div>
  </header>

  <h1>Rooftop solar proposal</h1>
  <p class="lead">Prepared for <strong>{{ $summary->customerAddress }}</strong></p>

  <div class="stat-grid">
    <div class="stat">
      <div class="label">Panels</div>
      <div class="value">{{ $summary->layout->totalPanels }}<span class="unit">modules</span></div>
    </div>
    <div class="stat">
      <div class="label">Capacity</div>
      <div class="value">{{ number_format($summary->layout->totalKwp, 2) }}<span class="unit">kWp</span></div>
    </div>
    <div class="stat">
      <div class="label">Annual production</div>
      <div class="value">{{ number_format($summary->layout->annualKwh, 0) }}<span class="unit">kWh</span></div>
    </div>
    <div class="stat">
      <div class="label">Investment</div>
      <div class="value">{{ number_format($summary->pricing->total, 0) }}<span class="unit">{{ $summary->pricing->currency }}</span></div>
    </div>
  </div>

  @if ($renderDataUri)
    <div class="render">
      <img src="{{ $renderDataUri }}" alt="Roof render">
      <div class="caption">Proposed panel layout on the selected roof (deterministic geometry; render is AI-enhanced).</div>
    </div>
  @endif

  <h2>Roof analysis</h2>
  <div class="two-col">
    <div>
      <div class="kv"><span class="k">Usable roof area</span><span class="v">{{ number_format($summary->analysis->usableRoofAreaM2, 1) }} m&sup2;</span></div>
      <div class="kv"><span class="k">Average pitch</span><span class="v">{{ number_format($summary->analysis->averagePitchDeg, 1) }}&deg;</span></div>
      <div class="kv"><span class="k">Dominant azimuth</span><span class="v">{{ number_format($summary->analysis->dominantAzimuthDeg, 0) }}&deg;</span></div>
      <div class="kv"><span class="k">Sunshine hours / year</span><span class="v">{{ number_format($summary->analysis->averageSunshineHoursPerYear, 0) }} h</span></div>
    </div>
    <div>
      <div class="kv"><span class="k">Shading score</span><span class="v">{{ number_format($summary->analysis->shadingScore, 2) }}</span></div>
      <div class="kv"><span class="k">Imagery quality</span><span class="v">{{ $summary->analysis->imageryQuality }}</span></div>
      <div class="kv"><span class="k">Imagery date</span><span class="v">{{ $summary->analysis->imageryDate }}</span></div>
      <div class="kv"><span class="k">Usable segments</span><span class="v">{{ $summary->analysis->usableSegments }}</span></div>
    </div>
  </div>

  <h2>Panel specification</h2>
  <div class="two-col">
    <div>
      <div class="kv"><span class="k">Module</span><span class="v">{{ $summary->panelSpec['model'] ?? 'Generic module' }}</span></div>
      <div class="kv"><span class="k">Panel dimensions</span><span class="v">{{ number_format((float)($summary->panelSpec['width_m'] ?? 0), 3) }} &times; {{ number_format((float)($summary->panelSpec['height_m'] ?? 0), 3) }} m</span></div>
      <div class="kv"><span class="k">Rated power</span><span class="v">{{ $summary->panelSpec['watt_peak'] ?? 0 }} Wp</span></div>
    </div>
    <div>
      <div class="kv"><span class="k">Efficiency</span><span class="v">{{ number_format((float)($summary->panelSpec['efficiency'] ?? 0) * 100, 1) }} %</span></div>
      <div class="kv"><span class="k">Setback</span><span class="v">{{ number_format((float)($summary->panelSpec['setback_m'] ?? 0), 2) }} m</span></div>
      <div class="kv"><span class="k">Row / column gap</span><span class="v">{{ number_format((float)($summary->panelSpec['row_gap_m'] ?? 0), 2) }} / {{ number_format((float)($summary->panelSpec['col_gap_m'] ?? 0), 2) }} m</span></div>
    </div>
  </div>

  <h2>Investment breakdown</h2>
  <table>
    <thead>
      <tr>
        <th>Item</th>
        <th class="num">Qty</th>
        <th>Unit</th>
        <th class="num">Unit price</th>
        <th class="num">Subtotal</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($summary->pricing->lineItems as $li)
        <tr>
          <td>{{ $li['label'] }}</td>
          <td class="num">{{ number_format((float) $li['qty'], 2) }}</td>
          <td>{{ $li['unit'] }}</td>
          <td class="num">{{ number_format((float) $li['unit_price'], 2) }} {{ $summary->pricing->currency }}</td>
          <td class="num">{{ number_format((float) $li['subtotal'], 2) }} {{ $summary->pricing->currency }}</td>
        </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr><td colspan="4" class="num">Equipment subtotal</td><td class="num">{{ number_format($summary->pricing->equipmentCost, 2) }} {{ $summary->pricing->currency }}</td></tr>
      <tr><td colspan="4" class="num">Labour subtotal</td><td class="num">{{ number_format($summary->pricing->laborCost, 2) }} {{ $summary->pricing->currency }}</td></tr>
      <tr><td colspan="4" class="num">Subtotal</td><td class="num">{{ number_format($summary->pricing->subtotal, 2) }} {{ $summary->pricing->currency }}</td></tr>
      <tr><td colspan="4" class="num">Margin</td><td class="num">{{ number_format($summary->pricing->margin, 2) }} {{ $summary->pricing->currency }}</td></tr>
      <tr><td colspan="4" class="num">Discount</td><td class="num">&minus;{{ number_format($summary->pricing->discount, 2) }} {{ $summary->pricing->currency }}</td></tr>
      <tr><td colspan="4" class="num">Net before VAT</td><td class="num">{{ number_format($summary->pricing->netBeforeVat, 2) }} {{ $summary->pricing->currency }}</td></tr>
      <tr><td colspan="4" class="num">VAT</td><td class="num">{{ number_format($summary->pricing->vat, 2) }} {{ $summary->pricing->currency }}</td></tr>
      <tr class="total"><td colspan="4" class="num">TOTAL</td><td class="num">{{ number_format($summary->pricing->total, 2) }} {{ $summary->pricing->currency }}</td></tr>
    </tfoot>
  </table>

  <h2>Savings over {{ $summary->savings->horizonYears }} years</h2>
  <div class="stat-grid">
    <div class="stat">
      <div class="label">First-year savings</div>
      <div class="value">{{ number_format($summary->savings->firstYearSavings, 0) }}<span class="unit">{{ $summary->pricing->currency }}</span></div>
    </div>
    <div class="stat">
      <div class="label">Lifetime savings</div>
      <div class="value">{{ number_format($summary->savings->lifetimeSavings, 0) }}<span class="unit">{{ $summary->pricing->currency }}</span></div>
    </div>
    <div class="stat">
      <div class="label">Payback</div>
      <div class="value">{{ number_format($summary->savings->paybackYears, 1) }}<span class="unit">years</span></div>
    </div>
    <div class="stat">
      <div class="label">Grid tariff</div>
      <div class="value">{{ number_format($summary->savings->gridTariffPerKwh, 2) }}<span class="unit">{{ $summary->pricing->currency }}/kWh</span></div>
    </div>
  </div>
  <table>
    <thead>
      <tr>
        <th>Year</th>
        <th class="num">Production (kWh)</th>
        <th class="num">Self-consumed</th>
        <th class="num">Exported</th>
        <th class="num">Annual savings</th>
        <th class="num">Cumulative</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($summary->savings->yearly as $row)
        <tr>
          <td>{{ $row['year'] }}</td>
          <td class="num">{{ number_format((float) $row['production_kwh'], 0) }}</td>
          <td class="num">{{ number_format((float) $row['self_consumed_kwh'], 0) }}</td>
          <td class="num">{{ number_format((float) $row['exported_kwh'], 0) }}</td>
          <td class="num">{{ number_format((float) $row['annual_savings'], 2) }} {{ $summary->pricing->currency }}</td>
          <td class="num">{{ number_format((float) $row['cumulative_savings'], 2) }} {{ $summary->pricing->currency }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <h2>Contact</h2>
  <div class="two-col">
    <div>
      <div class="kv"><span class="k">Company</span><span class="v">{{ $summary->branding['company_name'] ?? '' }}</span></div>
      <div class="kv"><span class="k">Email</span><span class="v">{{ $summary->branding['contact_email'] ?? '' }}</span></div>
      <div class="kv"><span class="k">Phone</span><span class="v">{{ $summary->branding['contact_phone'] ?? '' }}</span></div>
    </div>
    <div>
      <div class="kv"><span class="k">Website</span><span class="v">{{ $summary->branding['website'] ?? '' }}</span></div>
      <div class="kv"><span class="k">Address</span><span class="v">{{ $summary->branding['address'] ?? '' }}</span></div>
      <div class="kv"><span class="k">Proposal ID</span><span class="v">{{ $summary->projectId }}</span></div>
    </div>
  </div>

  <footer>
    <p class="disclaimer">{{ $summary->branding['disclaimer'] ?? '' }}</p>
  </footer>
</div>
</body>
</html>
