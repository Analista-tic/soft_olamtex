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
    .kpi-box { display: inline-block; border: 1px solid #d1d5db; border-radius: 6px; padding: 8px 16px; margin-right: 12px; margin-bottom: 14px; min-width: 130px; }
    .kpi-label { font-size: 7pt; color: #666; text-transform: uppercase; letter-spacing: .06em; }
    .kpi-value { font-size: 16pt; font-weight: 700; color: #1e3d70; }
    .badge { display: inline-block; padding: 1px 7px; border-radius: 10px; font-size: 7pt; font-weight: 600; }
    .badge-ok  { background: #dcfce7; color: #166534; }
    .badge-pnd { background: #fef9c3; color: #854d0e; }
    .badge-no  { background: #fee2e2; color: #991b1b; }
</style>
</head>
<body>
@include('pdf.reports._base')

<div>
    <div class="kpi-box">
        <div class="kpi-label">Total rollos</div>
        <div class="kpi-value">{{ $rolls->count() }}</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-label">Metros disponibles</div>
        <div class="kpi-value">{{ number_format($totalMeters, 2, ',', '.') }} mts</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>N° Rollo</th>
            <th>Tela / Ítem</th>
            <th>Color</th>
            <th>Cantidad inicial</th>
            <th>Disponible</th>
            <th>Lote</th>
            <th>Ubicación</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rolls as $roll)
            @php
                $available = max(0, (float)$roll->quantity + (float)$roll->length_adjustment_meters - (float)$roll->actual_output);
                $status    = $roll->approval_status ?? '';
                $label     = $approvalLabels[$status] ?? $status;
                $badgeClass = match($status) {
                    'approved'         => 'badge-ok',
                    'pending_approval' => 'badge-pnd',
                    default            => 'badge-no',
                };
            @endphp
            <tr>
                <td>{{ $roll->roll_number }}</td>
                <td>{{ $roll->item?->name ?? '—' }}</td>
                <td>{{ $roll->internal_color_name ?? '—' }}</td>
                <td>{{ number_format((float)$roll->quantity, 2, ',', '.') }} mts</td>
                <td><strong>{{ number_format($available, 2, ',', '.') }} mts</strong></td>
                <td>{{ $roll->batch ?? '—' }}</td>
                <td>{{ $roll->location ?? '—' }}</td>
                <td><span class="badge {{ $badgeClass }}">{{ $label }}</span></td>
            </tr>
        @empty
            <tr><td colspan="8" style="text-align:center; color:#888; padding:16px;">Sin registros.</td></tr>
        @endforelse
    </tbody>
</table>

<div style="margin-top:14px; font-size:7pt; color:#aaa; text-align:right;">
    ForU Tex &mdash; {{ $printDate }} &mdash; Total registros: {{ $rolls->count() }}
</div>
</body>
</html>
