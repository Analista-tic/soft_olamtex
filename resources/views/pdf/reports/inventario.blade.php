<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>{{ $title }}</title>
<style>
    body { font-family: Arial, sans-serif; font-size: 8pt; color: #222; margin: 0; padding: 16px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #1e3d70; color: #fff; padding: 5px 7px; text-align: left; font-size: 7.5pt; }
    td { padding: 4px 7px; border-bottom: 1px solid #e5e7eb; font-size: 7.5pt; }
    tr:nth-child(even) td { background: #f8fafc; }
    .kpi-box { display: inline-block; border: 1px solid #d1d5db; border-radius: 6px; padding: 8px 16px; margin-right: 12px; margin-bottom: 14px; min-width: 110px; }
    .kpi-label { font-size: 7pt; color: #666; text-transform: uppercase; letter-spacing: .06em; }
    .kpi-value { font-size: 16pt; font-weight: 700; color: #1e3d70; }
</style>
</head>
<body>
@include('pdf.reports._base')

<div>
    <div class="kpi-box">
        <div class="kpi-label">Total ítems</div>
        <div class="kpi-value">{{ $summary['total'] }}</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-label">Con stock</div>
        <div class="kpi-value">{{ $summary['con_stock'] }}</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-label">Sin stock</div>
        <div class="kpi-value">{{ $summary['sin_stock'] }}</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>Ítem</th>
            <th>Bodega</th>
            <th>Cantidad</th>
            <th>Stock mín.</th>
            <th>Stock máx.</th>
            <th>Costo promedio</th>
        </tr>
    </thead>
    <tbody>
        @forelse($items as $item)
            <tr>
                <td>{{ $item->item?->name ?? '—' }}</td>
                <td>{{ $item->warehouse?->name ?? '—' }}</td>
                <td>{{ number_format((float)$item->quantity, 2, ',', '.') }}</td>
                <td>{{ $item->stock_min ?? '—' }}</td>
                <td>{{ $item->stock_max ?? '—' }}</td>
                <td>
                    {{ $item->weighted_average_price
                        ? '$' . number_format((float)$item->weighted_average_price, 0, ',', '.')
                        : '—' }}
                </td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center; color:#888; padding:16px;">Sin registros.</td></tr>
        @endforelse
    </tbody>
</table>

<div style="margin-top:14px; font-size:7pt; color:#aaa; text-align:right;">
    ForU Tex &mdash; {{ $printDate }} &mdash; Total registros: {{ $items->count() }}
</div>
</body>
</html>
