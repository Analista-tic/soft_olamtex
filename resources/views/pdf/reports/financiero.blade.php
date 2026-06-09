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
    .badge-pending   { background: #fef9c3; color: #854d0e; }
    .badge-approved  { background: #dcfce7; color: #166534; }
    .badge-rejected  { background: #fee2e2; color: #991b1b; }
    .badge-completed { background: #e0e7ff; color: #3730a3; }
    .badge-default   { background: #f1f5f9; color: #475569; }
    .filter-info { font-size: 7.5pt; color: #555; margin-bottom: 10px; }
</style>
</head>
<body>
@include('pdf.reports._base')

@if($dateFrom || $dateTo)
<div class="filter-info">
    Período:
    @if($dateFrom) desde {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} @endif
    @if($dateTo) hasta {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }} @endif
</div>
@endif

<div>
    <div class="kpi-box">
        <div class="kpi-label">Total docs.</div>
        <div class="kpi-value">{{ $summary['total'] }}</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-label">Pendientes</div>
        <div class="kpi-value">{{ $summary['pendientes'] }}</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-label">Aprobados</div>
        <div class="kpi-value">{{ $summary['aprobados'] }}</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>N° Documento</th>
            <th>Tipo</th>
            <th>Proveedor</th>
            <th>Fecha</th>
            <th>Estado</th>
            <th>Aprobación</th>
        </tr>
    </thead>
    <tbody>
        @forelse($docs as $doc)
            @php
                $status         = $doc->status ?? '';
                $approvalStatus = $doc->approval_status ?? '';
                $statusLabel    = $statusLabels[$status] ?? $status;
                $approvalLabel  = $statusLabels[$approvalStatus] ?? $approvalStatus;
                $statusClass    = 'badge-' . ($status ?: 'default');
                $approvalClass  = 'badge-' . ($approvalStatus ?: 'default');
            @endphp
            <tr>
                <td><strong>{{ $doc->document_number ?? '—' }}</strong></td>
                <td>{{ $docTypeLabels[$doc->document_type ?? ''] ?? ($doc->document_type ?? '—') }}</td>
                <td>{{ $doc->contact?->name ?? '—' }}</td>
                <td>{{ $doc->date ?? '—' }}</td>
                <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                <td><span class="badge {{ $approvalClass }}">{{ $approvalLabel }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center; color:#888; padding:16px;">Sin registros.</td></tr>
        @endforelse
    </tbody>
</table>

<div style="margin-top:14px; font-size:7pt; color:#aaa; text-align:right;">
    ForU Tex &mdash; {{ $printDate }} &mdash; Total registros: {{ $docs->count() }}
</div>
</body>
</html>
