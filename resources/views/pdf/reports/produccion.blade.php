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
    .badge { display: inline-block; padding: 1px 7px; border-radius: 10px; font-size: 7pt; font-weight: 600; }
    .badge-active    { background: #dcfce7; color: #166534; }
    .badge-completed { background: #e0e7ff; color: #3730a3; }
    .badge-cancelled { background: #fee2e2; color: #991b1b; }
    .badge-default   { background: #f1f5f9; color: #475569; }
</style>
</head>
<body>
@include('pdf.reports._base')

<div>
    <div class="kpi-box">
        <div class="kpi-label">Total</div>
        <div class="kpi-value">{{ $summary['total'] }}</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-label">Activas</div>
        <div class="kpi-value">{{ $summary['activas'] }}</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-label">Completadas</div>
        <div class="kpi-value">{{ $summary['completadas'] }}</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>Código OP</th>
            <th>Referencia</th>
            <th>Temporada</th>
            <th>Unidades</th>
            <th>Proceso actual</th>
            <th>Fecha estimada</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @forelse($orders as $order)
            @php
                $status = $order->status ?? '';
                $label  = $statusLabels[$status] ?? $status;
                $badgeClass = match($status) {
                    'active'    => 'badge-active',
                    'completed' => 'badge-completed',
                    'cancelled' => 'badge-cancelled',
                    default     => 'badge-default',
                };
            @endphp
            <tr>
                <td><strong>{{ $order->op_code ?? '—' }}</strong></td>
                <td>{{ $order->productSheet?->reference ?? '—' }}</td>
                <td>{{ $order->season ?? '—' }}</td>
                <td>{{ $order->units ?? '—' }}</td>
                <td>{{ $order->current_process ?? '—' }}</td>
                <td>{{ $order->estimated_delivery_date ?? '—' }}</td>
                <td><span class="badge {{ $badgeClass }}">{{ $label }}</span></td>
            </tr>
        @empty
            <tr><td colspan="7" style="text-align:center; color:#888; padding:16px;">Sin registros.</td></tr>
        @endforelse
    </tbody>
</table>

<div style="margin-top:14px; font-size:7pt; color:#aaa; text-align:right;">
    ForU Tex &mdash; {{ $printDate }} &mdash; Total registros: {{ $orders->count() }}
</div>
</body>
</html>
