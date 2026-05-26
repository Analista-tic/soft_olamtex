@php
    if (!function_exists('money')) {
        function money($value) {
            if ($value === null) return '-';
            return '$' . number_format((float) $value, 0, ',', '.');
        }
    }
    if (!function_exists('num')) {
        function num($value, $decimals = 2) {
            if ($value === null) return '-';
            return number_format((float) $value, $decimals, ',', '.');
        }
    }

    // ── Extraer colores únicos desde rollos ──
    $producedColors = [];
    if (!empty($rolls['lines'])) {
        foreach ($rolls['lines'] as $line) {
            $cName = trim($line['color'] ?? $line['colors'] ?? '');
            $cCode = trim($line['colorCode'] ?? '');
            if ($cName !== '' && !isset($producedColors[$cName])) {
                $producedColors[$cName] = $cCode;
            }
        }
    }

    // ── Separar insumos por etapa ──
    $suppliesConf  = [];
    $suppliesTerm  = [];
    $suppliesOther = [];
    if (!empty($supplies)) {
        foreach ($supplies as $s) {
            $stage = strtoupper(trim($s['stage'] ?? ''));
            if (str_contains($stage, 'CONF')) {
                $suppliesConf[] = $s;
            } elseif (str_contains($stage, 'TERM') || str_contains($stage, 'TERMI')) {
                $suppliesTerm[] = $s;
            } else {
                $suppliesOther[] = $s;
            }
        }
    }
    if (empty($suppliesConf) && empty($suppliesTerm) && !empty($supplies)) {
        $suppliesOther = $supplies;
    }

    // ── Tallas ──
    $sizesDisplay = is_array($basic['sizes'] ?? null) ? $basic['sizes'] : [];

    // ── Size measurements ──
    $hasSizeMeasurements  = false;
    $sizeMeasurementRows  = [];
    if (!empty($supplies)) {
        foreach ($supplies as $s) {
            if (!empty($s['sizeMeasurements']) && is_array($s['sizeMeasurements'])) {
                $hasSizeMeasurements = true;
                $sizeMeasurementRows[] = $s;
            }
        }
    }
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Ficha Técnica — {{ $basic['opCode'] ?? 'OP' }}</title>
<style>
/* ════════════════════════════════════════════════════════════
   BASE  —  Letter Landscape: 279.4 × 215.9 mm
   ════════════════════════════════════════════════════════════ */
@page { size: letter landscape; margin: 30mm 18mm 18mm 18mm; }
* { margin:0; padding:0; box-sizing:border-box; }
html, body {
    font-family: Arial, Helvetica, sans-serif;
    background: #fff;
    color: #1e2533;
    font-size: 9pt;
    line-height: 1.35;
    text-transform: uppercase;
}

/* ════════════════════════════════════════════════════════════
   HEADER BAND
   ════════════════════════════════════════════════════════════ */
.hdr { width:100%; border-collapse:separate; border-spacing:0; margin-bottom:0; border-radius:7px; overflow:hidden; }
.hdr td { vertical-align:middle; padding:5px 10px; }
.hdr-logo {
    width:120px; text-align:center;
    background:#fff;
    border-right:3px solid rgba(255,255,255,.35);
}
.hdr-logo img { max-height:30px; max-width:110px; }
.hdr-logo .logo-txt { font-size:11pt; font-weight:800; letter-spacing:2px; color:#1e3d70; }
.hdr-center { text-align:center; }
.hdr-center h1 {
    font-size:13pt; font-weight:700; color:#fff;
    margin:0; letter-spacing:.8px; text-transform:uppercase;
}
.hdr-center .sub { font-size:9pt; color:rgba(255,255,255,.78); margin-top:3px; }
.hdr-meta { width:165px; text-align:right; font-size:9pt; color:rgba(255,255,255,.8); }
.hdr-meta strong { color:#fff; display:block; }
/* accent bar under header */
.hdr-bar { height:3px; margin-bottom:5px; }

/* ════════════════════════════════════════════════════════════
   TWO-COLUMN LAYOUT
   ════════════════════════════════════════════════════════════ */
.layout { width:100%; border-collapse:collapse; }
.layout > tbody > tr > td { vertical-align:top; padding:0; }
.col-data { padding-right:9px; }
/* widths set inline per page */

/* ════════════════════════════════════════════════════════════
   INFO CARDS  (key → value tiles)
   ════════════════════════════════════════════════════════════ */
.info-cards { width:100%; border-collapse:separate; border-spacing:0; margin-bottom:6px; border-radius:7px; border:1px solid #dde5f0; }
.info-cards td {
    padding:5px 8px; border-right:1px solid #dde5f0; border-bottom:1px solid #dde5f0;
    background:#f6f9ff; vertical-align:top; text-align:center;
}
.info-cards td:last-child { border-right:none; }
.info-cards tr:last-child td { border-bottom:none; }
.info-cards tr:first-child td:first-child { border-top-left-radius:7px; }
.info-cards tr:first-child td:last-child  { border-top-right-radius:7px; }
.info-cards tr:last-child  td:first-child { border-bottom-left-radius:7px; }
.info-cards tr:last-child  td:last-child  { border-bottom-right-radius:7px; }
.ic-lbl {
    display:block; font-size:9pt; font-weight:700;
    text-transform:uppercase; letter-spacing:.4px;
    color:#6278a0; margin-bottom:2px; text-align:center;
}
.ic-val { font-size:9pt; font-weight:600; color:#1e2533; text-align:center; display:block; }

.summary-table td { text-align:center; vertical-align:bottom; }

/* ════════════════════════════════════════════════════════════
   SECTION TITLES
   ════════════════════════════════════════════════════ */
.sec-title {
    font-size:9pt; font-weight:700; text-transform:uppercase;
    letter-spacing:.6px; padding:5px 9px;
    margin:0 0 0 0; border-left:4px solid;
    border-radius:7px 7px 0 0;
    page-break-before:avoid; page-break-after:avoid;
}
.sec-divider {
    height:8mm; border-bottom:1.5px solid #c5d3e8; margin:15px 0 0 0;
    page-break-after:avoid;
}
.sec-sub {
    font-size:9pt; font-weight:700;
    padding:3px 0 2px 0; margin:5px 0 3px 0;
    border-bottom:2px solid;
}

/* ════════════════════════════════════════════════════════════
   DATA TABLES
   ════════════════════════════════════════════════════════════ */
table.dt { width:100%; border-collapse:separate; border-spacing:0; margin-bottom:5px; page-break-inside:avoid; border-radius:0 0 7px 7px; border:1px solid #c8d4e8; }
table.dt th {
    font-size:9pt; font-weight:700; text-transform:uppercase;
    letter-spacing:.3px; padding:3px 5px;
    text-align:center; white-space:nowrap;
    border-right:.5px solid rgba(255,255,255,0.25);
    border-bottom:.5px solid rgba(255,255,255,0.25);
    page-break-after:avoid;
}
table.dt th:last-child { border-right:none; }
table.dt td {
    font-size:9pt; padding:2.8px 5px;
    border-right:.5px solid #dde5f0;
    border-bottom:.5px solid #dde5f0;
    vertical-align:top;
    text-align:center;
}
table.dt td:last-child { border-right:none; }
table.dt tbody tr:last-child td { border-bottom:none; }
table.dt tfoot tr:last-child td { border-bottom:none; }
table.dt tbody tr:nth-child(even) { background:#edf8fd; }
table.dt .row-total { font-weight:700; }
table.dt .row-stage td {
    font-weight:700; font-size:9pt;
    text-transform:uppercase; letter-spacing:.3px; padding:3.5px 6px;
}
table.dt td.tr { text-align:right; }
table.dt td.tc { text-align:center; }
/* Esquinas redondeadas — solo abajo (el sec-title cubre las de arriba) */
table.dt tbody tr:last-child td:first-child  { border-bottom-left-radius:7px; }
table.dt tbody tr:last-child td:last-child   { border-bottom-right-radius:7px; }
table.dt tfoot tr:last-child td:first-child  { border-bottom-left-radius:7px; }
table.dt tfoot tr:last-child td:last-child   { border-bottom-right-radius:7px; }

/* ════════════════════════════════════════════════════════════
   DRAWING  (right column)
   ════════════════════════════════════════════════════════════ */
.draw-wrap { margin-bottom:6px; }
.draw-lbl {
    font-size:9pt; font-weight:700; text-transform:uppercase;
    letter-spacing:.5px; color:#fff; padding:3px 7px;
    border-radius:7px 7px 0 0;
}
.draw-box {
    border:1.5px solid; padding:6px;
    background:#f8fbff; text-align:center;
    border-radius:0 0 7px 7px;
}
.draw-box img { max-width:100%; max-height:354px; display:block; margin:0 auto; }
.draw-empty {
    border:2px dashed #b8cce4; background:#f4f7ff;
    text-align:center; padding:0;
}
.draw-empty-inner { font-size:9pt; color:#8a9bbf; }

/* ════════════════════════════════════════════════════════════
   COLORS GRID
   ════════════════════════════════════════════════════════════ */
.clr-lbl {
    font-size:9pt; font-weight:700; text-transform:uppercase;
    letter-spacing:.4px; color:#fff; padding:3px 7px;
    margin-top:3px; border-radius:7px 7px 0 0;
}
.clr-grid { width:100%; border-collapse:separate; border-spacing:0; margin-bottom:5px; border-radius:0 0 7px 7px; border:1px solid #dde5f0; }
.clr-grid td {
    padding:4px 6px; text-align:center; vertical-align:middle;
    border-right:.5px solid #dde5f0; border-bottom:.5px solid #dde5f0; background:#f6f9ff;
}
.clr-grid td:last-child { border-right:none; }
.clr-grid tr:last-child td { border-bottom:none; }
.clr-grid tr:last-child td:first-child { border-bottom-left-radius:7px; }
.clr-grid tr:last-child td:last-child  { border-bottom-right-radius:7px; }
.clr-name { font-size:9pt; font-weight:700; color:#1e2533; }
.clr-code { font-size:9pt; font-weight:600; margin-top:1px; }

/* ════════════════════════════════════════════════════════════
   COST CARDS
   ════════════════════════════════════════════════════════════ */
.cost-cards { width:100%; border-collapse:collapse; margin-bottom:5px; }
.cost-cards td {
    padding:5px 8px; border:1px solid #dde5f0; background:#f6f9ff;
    vertical-align:top;
}
.cost-pos { color:#1a6e3a !important; font-weight:700; }
.cost-neg { color:#b03030 !important; font-weight:700; }

/* ════════════════════════════════════════════════════════════
   NOTES / WARNINGS
   ════════════════════════════════════════════════════════════ */
.note-box {
    padding:4px 8px; margin-bottom:4px;
    font-size:9pt; border-left:4px solid;
}
.warn-box {
    padding:4px 8px; margin-bottom:4px; font-size:9pt;
    border-left:4px solid #d48a0a; background:#fffbec; color:#6e4a00;
}

/* ════════════════════════════════════════════════════════════
   UTILITIES
   ════════════════════════════════════════════════════════════ */
.tr  { text-align:right; }
.tc  { text-align:center; }
.b   { font-weight:700; }
.nw  { white-space:nowrap; }
.sm  { font-size:9pt; }
.xs  { font-size:9pt; }
.mut { color:#7a8fa8; }
.page-break { margin-top:8mm; }
table.hdr { page-break-after: avoid; }
.page-frame { padding: 6mm 6mm 5mm 6mm; }

/* ════════════════════════════════════════════════════════════
   PAGE FOOTER
   ════════════════════════════════════════════════════════════ */
.pg-footer {
    font-size:9pt; color:#fff; text-align:center;
    padding:3px 0; margin-top:4px;
}
.rotated-text {
    writing-mode: vertical-lr;
    text-orientation: mixed;
    white-space: nowrap;
    padding: 5px 2px;
    height: 100%;
    vertical-align: middle;
    text-align: center;
    font-weight: 700;
}

/* ════════════════════════════════════════════════════════════
    COLOR ÚNICO AZUL SERENO
    Header  #2a9dc7  |  Acento #57c8f4  |  Claro #edf8fd
    ════════════════════════════════════════════════════════════ */

/* — PRECOSTEO — */
.n-hdr  { background:#2a9dc7; }
.n-bar  { background:#57c8f4; }
.n-sec  { background:#2a9dc7; border-left-color:#57c8f4; color:#fff; }
.n-sub  { color:#0d3d52; border-bottom-color:#57c8f4; }
.n-th   { background:#c8ecf8; color:#0d3d52; border-color:#b0e0f5; }
.n-draw { border-color:#b0e0f5; }
.n-dlbl { background:#2a9dc7; }
.n-clbl { background:#2a9dc7; }
.n-code { color:#2a9dc7; }
.n-stg td { background:#c8ecf8 !important; color:#0d3d52; }
.n-tot td { background:#c8ecf8 !important; font-weight:700; }
.n-foot { background:#2a9dc7; }
.n-note { background:#edf8fd; border-left-color:#57c8f4; color:#0d3d52; }

/* — CORTE — */
.g-hdr  { background:#2a9dc7; }
.g-bar  { background:#57c8f4; }
.g-sec  { background:#2a9dc7; border-left-color:#57c8f4; color:#fff; }
.g-sub  { color:#0d3d52; border-bottom-color:#57c8f4; }
.g-th   { background:#c8ecf8; color:#0d3d52; border-color:#b0e0f5; }
.g-draw { border-color:#b0e0f5; }
.g-dlbl { background:#2a9dc7; }
.g-clbl { background:#2a9dc7; }
.g-code { color:#2a9dc7; }
.g-stg td { background:#c8ecf8 !important; color:#0d3d52; }
.g-tot td { background:#c8ecf8 !important; font-weight:700; }
.g-foot { background:#2a9dc7; }
.g-note { background:#edf8fd; border-left-color:#57c8f4; color:#0d3d52; }

/* — CONFECCIÓN — */
.p-hdr  { background:#2a9dc7; }
.p-bar  { background:#57c8f4; }
.p-sec  { background:#2a9dc7; border-left-color:#57c8f4; color:#fff; }
.p-sub  { color:#0d3d52; border-bottom-color:#57c8f4; }
.p-th   { background:#c8ecf8; color:#0d3d52; border-color:#b0e0f5; }
.p-draw { border-color:#b0e0f5; }
.p-dlbl { background:#2a9dc7; }
.p-clbl { background:#2a9dc7; }
.p-code { color:#2a9dc7; }
.p-stg td { background:#c8ecf8 !important; color:#0d3d52; }
.p-tot td { background:#c8ecf8 !important; font-weight:700; }
.p-foot { background:#2a9dc7; }
.p-note { background:#edf8fd; border-left-color:#57c8f4; color:#0d3d52; }

/* — TERMINACIÓN — */
.a-hdr  { background:#2a9dc7; }
.a-bar  { background:#57c8f4; }
.a-sec  { background:#2a9dc7; border-left-color:#57c8f4; color:#fff; }
.a-sub  { color:#0d3d52; border-bottom-color:#57c8f4; }
.a-th   { background:#c8ecf8; color:#0d3d52; border-color:#b0e0f5; }
.a-draw { border-color:#b0e0f5; }
.a-dlbl { background:#2a9dc7; }
.a-clbl { background:#2a9dc7; }
.a-code { color:#2a9dc7; }
.a-stg td { background:#c8ecf8 !important; color:#0d3d52; }
.a-tot td { background:#c8ecf8 !important; font-weight:700; }
.a-foot { background:#2a9dc7; }
.a-note { background:#edf8fd; border-left-color:#57c8f4; color:#0d3d52; }

/* — COMPACCIÓN LOCAL PÁG. 3 y 4 — */
.p-page .info-cards,
.a-page .info-cards { margin-bottom:4px; }

.p-page .sec-title,
.a-page .sec-title {
    padding:4px 8px;
    margin:0 0 2px 0;
}

.p-page table.dt,
.a-page table.dt { margin-bottom:4px; }

.p-page .draw-wrap,
.a-page .draw-wrap { margin-bottom:4px; }

.p-page .pg-footer,
.a-page .pg-footer { margin-top:3px; }
</style>
</head>
<body>
<div class="page-frame">

{{-- ╔══════════════════════════════════════════════════════════════╗ --}}
{{-- ║  PÁG. 1  ·  FICHA TÉCNICA                                      ║ --}}
{{-- ╚══════════════════════════════════════════════════════════════╝ --}}

{{-- ── HEADER ── --}}
<table class="hdr n-hdr">
    <tr>
        <td class="hdr-logo">
            @if(file_exists(public_path('images/olamtex.png')))
                <img src="{{ public_path('images/olamtex.png') }}" alt="Olamtex" />
            @else
                <span class="logo-txt">OLAMTEX</span>
            @endif
        </td>
        <td class="hdr-center">
            <h1>Ficha Técnica</h1>
            <div class="sub">{{ $basic['opCode'] ?? '-' }} &nbsp;—&nbsp; {{ $reference ?? '-' }}</div>
        </td>
        <td class="hdr-meta">
            <strong>{{ $printDate }}</strong>
            {{ $basic['reference'] ?? '-' }}
        </td>
    </tr>
</table>
<div class="hdr-bar n-bar"></div>

{{-- ── INFO BÁSICA + DIBUJO PLANO ── --}}
<table class="layout">
    <tr>
        {{-- IZQUIERDA 50%: Información general --}}
        <td style="width:50%; vertical-align:top; padding-right:6px;">
            <table class="info-cards">
                <tr>
                    <td style="width:33.33%;"><span class="ic-lbl">Orden producción</span><span class="ic-val b">{{ $basic['opCode'] ?? '-' }}</span></td>
                    <td style="width:33.33%;"><span class="ic-lbl">Referencia</span><span class="ic-val">{{ $basic['reference'] ?? '-' }}</span></td>
                    <td style="width:33.34%;"><span class="ic-lbl">Fecha</span><span class="ic-val">{{ $basic['date'] ?? '-' }}</span></td>
                </tr>
                <tr>
                    <td><span class="ic-lbl">Destino</span><span class="ic-val">{{ $basic['destination'] ?? '-' }}</span></td>
                    <td><span class="ic-lbl">Sección</span><span class="ic-val">{{ $basic['section'] ?? '-' }}</span></td>
                    <td><span class="ic-lbl">Grupo</span><span class="ic-val">{{ $basic['group'] ?? '-' }}</span></td>
                </tr>
                <tr>
                    <td><span class="ic-lbl">Marca</span><span class="ic-val">{{ $basic['brand'] ?? '-' }}</span></td>
                    <td><span class="ic-lbl">Mundo</span><span class="ic-val">{{ $basic['world'] ?? '-' }}</span></td>
                    <td><span class="ic-lbl">Temporada</span><span class="ic-val">{{ $basic['season'] ?? '-' }}</span></td>
                </tr>
                <tr>
                    <td><span class="ic-lbl">Unidades</span><span class="ic-val b">{{ $basic['units'] ?? '-' }}</span></td>
                    <td><span class="ic-lbl">Prioridad</span><span class="ic-val">{{ $basic['priority'] ?? '-' }}</span></td>
                    <td><span class="ic-lbl">E-commerce</span><span class="ic-val">{{ !empty($basic['ecommerce']) ? 'Sí' : 'No' }}</span></td>
                </tr>
                <tr>
                    <td><span class="ic-lbl">Base tela</span><span class="ic-val">{{ $basic['base'] ?? '-' }}</span></td>
                    <td><span class="ic-lbl">Base hilaza</span><span class="ic-val">{{ $basic['baseYarn'] ?? '-' }}</span></td>
                    <td><span class="ic-lbl">Descripción</span><span class="ic-val sm">{{ $basic['description'] ?? '-' }}</span></td>
                </tr>
                @if(!empty($additionalInfo['cutting']['sizes']))
                    @php
                        $szRows       = $additionalInfo['cutting']['sizes'];
                        $totalUnitsP1 = array_sum(array_column($szRows, 'units'));
                    @endphp
                    <tr>
                        <td colspan="3" style="padding-top:4px;">
                            <span class="ic-lbl">Tallas / Curva / Unidades</span>
                            <div style="margin-top:3px;overflow:hidden;">
                                <table style="width:100%;font-size:7pt;border-collapse:collapse;">
                                    <thead><tr>
                                        @foreach($szRows as $sz)
                                            <th style="text-align:center;border:1px solid #c8d4e8;padding:2px 4px;background:#eaf1fb;color:#1a3a5c;font-weight:700;">{{ $sz['size'] ?? '-' }}</th>
                                        @endforeach
                                        <th style="text-align:center;border:1px solid #c8d4e8;padding:2px 4px;background:#d6e6f7;color:#1a3a5c;font-weight:700;">Total</th>
                                    </tr></thead>
                                    <tbody>
                                        <tr>
                                            @foreach($szRows as $sz)
                                                <td style="text-align:center;border:1px solid #c8d4e8;padding:2px 3px;font-size:6.5pt;">
                                                    <div style="color:#6278a0;">cv: {{ $sz['curve'] ?? '-' }}</div>
                                                    <div style="font-weight:700;">{{ $sz['units'] ?? 0 }}u</div>
                                                </td>
                                            @endforeach
                                            <td style="text-align:center;border:1px solid #c8d4e8;padding:2px 3px;font-weight:700;">{{ $totalUnitsP1 }}u</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                @elseif(!empty($sizesDisplay))
                    <tr>
                        <td colspan="3">
                            <span class="ic-lbl">Tallas</span>
                            <span class="ic-val b">{{ implode(' · ', $sizesDisplay) }}</span>
                        </td>
                    </tr>
                @endif
            </table>

            {{-- Procesos --}}
            @if(!empty($processes['processes']))
                <div class="sec-divider"></div>
<div class="sec-title n-sec">Procesos</div>
                <table class="dt">
                    <thead><tr>
                        <th class="n-th">Proceso</th>
                        <th class="n-th">Tipo</th>
                    </tr></thead>
                    <tbody>
                        @foreach($processes['processes'] as $proc)
                            <tr>
                                <td class="b nw">P{{ $proc['order'] ?? '-' }}</td>
                                <td>{{ $proc['type'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

        </td>

        {{-- DERECHA 50%: Dibujo plano + Colores --}}
        <td style="width:50%; vertical-align:top;">
            <div class="draw-wrap">
                <div class="draw-lbl n-dlbl">Dibujo Plano de Referencia</div>
                @if(!empty($drawingBase64))
                    <div class="draw-box n-draw" style="padding:0; overflow:hidden;">
                        <table style="width:100%; table-layout:fixed; border-collapse:collapse;" cellspacing="0" cellpadding="0">
                            <tr>
                                <td style="height:370px; padding:8px; overflow:hidden; text-align:center; vertical-align:middle;">
                                    <img src="{{ $drawingBase64 }}" alt="Dibujo plano" style="max-width:100%; max-height:354px; display:block; margin:0 auto;" />
                                </td>
                            </tr>
                        </table>
                    </div>
                @else
                    <div class="draw-empty n-draw" style="height:380px;">
                        <div class="draw-empty-inner" style="padding-top:170px;">Sin dibujo plano</div>
                    </div>
                @endif
            </div>
            @if(!empty($producedColors))
                <div class="clr-lbl n-clbl">Colores Producidos</div>
                <table class="clr-grid">
                    @php $ci=0; @endphp
                    @foreach($producedColors as $cN => $cC)
                        @if($ci % 3 === 0)<tr>@endif
                        <td>
                            <table style="width:100%;border-collapse:collapse;">
                                <tr>
                                    <td style="width:16px;vertical-align:middle;padding-right:5px;">
                                        @if($cC)
                                            <div style="width:10px;height:10px;border-radius:50%;background-color:{{ $cC }};border:1px solid #b0b8c8;"></div>
                                        @endif
                                    </td>
                                    <td style="vertical-align:middle;">
                                        <div class="clr-name">{{ $cN }}</div>
                                        @if($cC)<div class="clr-code n-code">{{ $cC }}</div>@endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                        @php $ci++; @endphp
                        @if($ci % 3 === 0)</tr>@endif
                    @endforeach
                    @if($ci % 3 !== 0)
                        @for($p=$ci%3; $p<3; $p++)<td></td>@endfor
                        </tr>
                    @endif
                </table>
            @endif
        </td>
    </tr>
</table>



{{-- ╔══════════════════════════════════════════════════════════════╗ --}}
{{-- ║  PÁG. 2  ·  CORTE Y NUMERACIÓN                              ║ --}}
{{-- ╚══════════════════════════════════════════════════════════════╝ --}}

            {{-- Materia Prima --}}
            @if(!empty($rawMaterials))
                <div class="sec-divider"></div>
<div class="sec-title g-sec">Materia Prima</div>
                <table class="dt">
                    <thead><tr>
                        <th class="g-th">Mat. Prima</th>
                        <th class="g-th">Tela / Artículo</th>
                        <th class="g-th">Proveedor</th>
                        <th class="tc g-th">Ancho</th>
                        <th class="tc g-th">Prom. Trazo</th>
                        <th class="tc g-th">Largo de Trazo</th>
                        <th class="tc g-th">Largo Liquidación</th>
                        <th class="tc g-th">Consumo</th>
                    </tr></thead>
                    <tbody>
                        @foreach($rawMaterials as $idx => $row)
                            <tr>
                                <td class="b nw">Tela {{ $idx+1 }}</td>
                                <td>{{ $row['tela'] ?? '-' }}</td>
                                <td class="sm">{{ $row['lastSupplierName'] ?? '-' }}</td>
                                <td class="tc">{{ isset($row['ancho']) ? num($row['ancho']).' '.($row['anchoUnit']??'') : '-' }}</td>
                                <td class="tc">{{ isset($row['promTrazo']) ? num($row['promTrazo'],1) : '-' }}</td>
                                <td class="tc">{{ isset($row['largoTrazo']) ? num($row['largoTrazo'],1) : '-' }}</td>
                                <td class="tc">{{ isset($row['largoLiq']) ? num($row['largoLiq'],1) : '-' }}</td>
                                <td class="tc b">{{ isset($row['consumo']) ? num($row['consumo'],1) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- Rollos por Trazo (Consumo de Corte) --}}
            @php
                $cutLines = $additionalInfo['cutFabricLines'] ?? [];
                $trazoGroups = [];
                foreach ($cutLines as $line) {
                    $tuid = $line['trazo_uid'] ?? '__none__';
                    if (!isset($trazoGroups[$tuid])) {
                        $trazoGroups[$tuid] = [
                            'fabric_type' => $line['fabric_type'] ?? null,
                            'prom_trazo'  => $line['prom_trazo'] ?? null,
                            'largo_trazo' => $line['largo_trazo'] ?? null,
                            'largo_liq'   => $line['largo_liq'] ?? null,
                            'lines'       => [],
                        ];
                    }
                    $trazoGroups[$tuid]['lines'][] = $line;
                }
            @endphp
            @if(!empty($trazoGroups))
                <div class="sec-divider"></div>
                <div class="sec-title g-sec">Rollos por Trazo</div>
                @foreach($trazoGroups as $tuid => $tGroup)
                    @php
                        $tLines    = $tGroup['lines'];
                        $tCount    = count($tLines);
                        $sumCapaP  = array_sum(array_map(fn($l) => (int)($l['layers_fabric'] ?? 0), $tLines));
                        $sumCapaL  = array_sum(array_map(fn($l) => (int)($l['layers_liquidation'] ?? 0), $tLines));
                        $sumCapaE  = array_sum(array_map(fn($l) => (int)($l['layers_herringbone'] ?? 0), $tLines));
                        $sumUsar   = array_sum(array_map(fn($l) => (float)($l['meters_per_roll'] ?? 0), $tLines));
                        $sumConsumo= array_sum(array_map(fn($l) => (float)($l['total_consumption'] ?? 0), $tLines));
                        $sumFalt   = array_sum(array_map(fn($l) => (float)($l['restante'] ?? 0), $tLines));
                    @endphp
                    <div style="page-break-inside: avoid; padding-top: 16px;">
                    <table style="width:100%;font-size:8pt;font-weight:700;margin:0 0 3px;color:#1a3a5c;border-collapse:collapse;">
                        <tr>
                            <td style="vertical-align:bottom;padding-bottom:1px;">
                                Trazo {{ $loop->iteration }}
                                @if($tGroup['fabric_type']) &nbsp;|&nbsp; <span style="font-weight:400;">{{ $tGroup['fabric_type'] }}</span> @endif
                                &nbsp;|&nbsp; {{ $tCount }} rollo{{ $tCount !== 1 ? 's' : '' }}
                                @if($tGroup['prom_trazo'] !== null) &nbsp;|&nbsp; Prom. Trazo: <span style="font-weight:400;">{{ num($tGroup['prom_trazo'],3) }} m</span> @endif
                            </td>
                            <td style="text-align:right;vertical-align:bottom;padding-bottom:1px;">
                                Capa Principal <span style="font-weight:400;">{{ $sumCapaP }}</span>
                                &nbsp;|&nbsp; Capa Liquidación <span style="font-weight:400;">{{ $sumCapaL }}</span>
                                &nbsp;|&nbsp; Capa Espiga <span style="font-weight:400;">{{ $sumCapaE }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight:400;padding-top:1px;">
                                @if($tGroup['largo_trazo'] !== null) Largo de Trazo: <strong>{{ num($tGroup['largo_trazo'],3) }} m</strong> @endif
                                @if($tGroup['largo_liq'] !== null) &nbsp;|&nbsp; Largo Liquidación: <strong>{{ num($tGroup['largo_liq'],3) }} m</strong> @endif
                            </td>
                            <td style="text-align:right;font-weight:400;padding-top:1px;">
                                Consumo Total: <strong style="color:#c0392b;">{{ num($sumConsumo,2) }} m</strong>
                            </td>
                        </tr>
                    </table>
                    <table class="dt" style="margin-bottom:0;font-size:6pt;">
                        <thead><tr>
                            <th class="g-th nw">Rollo</th>
                            <th class="g-th">Tela</th>
                            <th class="tc g-th">Ubic.</th>
                            <th class="tc g-th" style="white-space:normal;">Ancho<br>(m)</th>
                            <th class="tr g-th" style="white-space:normal;">Total<br>Metros</th>
                            <th class="tr g-th" style="white-space:normal;">Metros a<br>Usar</th>
                            <th class="tc g-th" style="white-space:normal;">Capa<br>Principal</th>
                            <th class="tc g-th" style="white-space:normal;">Capa<br>Liquidación</th>
                            <th class="tc g-th" style="white-space:normal;">Capa<br>Espiga</th>
                            <th class="tr g-th">Retazo</th>
                            <th class="tr g-th" style="white-space:normal;">Consumo<br>Total</th>
                            <th class="tr g-th">Faltante</th>
                        </tr></thead>
                        <tbody>
                            @foreach($tLines as $cl)
                                <tr>
                                    <td class="b nw">{{ $cl['roll_code'] ?? '-' }}</td>
                                    <td class="sm">{{ $cl['fabric_reference'] ?? '-' }}</td>
                                    <td class="tc sm">{{ $cl['location'] ?? '-' }}</td>
                                    <td class="tc">{{ isset($cl['roll_width_m']) ? num($cl['roll_width_m'],2) : '-' }}</td>
                                    <td class="tc">{{ isset($cl['roll_total_meters']) ? num($cl['roll_total_meters'],2) : '-' }}</td>
                                    <td class="tc">{{ isset($cl['meters_per_roll']) ? num($cl['meters_per_roll'],2) : '-' }}</td>
                                    <td class="tc">{{ $cl['layers_fabric'] ?? '-' }}</td>
                                    <td class="tc">{{ $cl['layers_liquidation'] ?? '-' }}</td>
                                    <td class="tc">{{ $cl['layers_herringbone'] ?? '-' }}</td>
                                    <td class="tc">{{ isset($cl['retazo_1']) ? num($cl['retazo_1'],2) : '-' }}</td>
                                    <td class="tc">{{ isset($cl['total_consumption']) ? num($cl['total_consumption'],2) : '-' }}</td>
                                    <td class="tc b" style="color:#c0392b;">{{ isset($cl['restante']) ? num($cl['restante'],2) : '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr style="font-weight:700;border-top:1px solid #b0b8c8;">
                                <td colspan="5" class="tr" style="font-size:5.5pt;color:#6278a0;padding-top:5px;padding-bottom:1px;">Total</td>
                                <td class="tc">{{ num($sumUsar,2) }}</td>
                                <td class="tc">{{ $sumCapaP }}</td>
                                <td class="tc">{{ $sumCapaL }}</td>
                                <td class="tc">{{ $sumCapaE }}</td>
                                <td class="tc">—</td>
                                <td class="tc">{{ num($sumConsumo,2) }}</td>
                                <td class="tc" style="color:#c0392b;">{{ num($sumFalt,2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                    </div>{{-- fin page-break-inside wrapper --}}
                @endforeach
            @endif

            {{-- Curvas por Talla y Reporte de Unidades Reales --}}
            <div style="overflow:hidden; margin-bottom:5px;">
                <div style="width:49%; display:inline-block; vertical-align:top; margin-right:1%; page-break-inside: avoid;">
                    @if(!empty($additionalInfo['cutting']['sizeCurves']))
                        @php
                            $sc = $additionalInfo['cutting']['sizeCurves'];
                            $totalPrincipal  = array_sum(array_column($sc, 'trazoPrincipal'));
                            $totalLiquidacion = array_sum(array_column($sc, 'liquidacion'));
                            $totalEspiga     = array_sum(array_column($sc, 'espiga'));
                        @endphp
                        <div class="sec-divider"></div>
                        <div class="sec-title g-sec">Curvas por Talla</div>
                        <table class="dt">
                            <thead><tr>
                                <th class="g-th">Tipo de Trazo</th>
                                @foreach($sc as $sz)
                                    <th class="tc g-th">{{ $sz['size'] }}</th>
                                @endforeach
                                <th class="tc g-th">Total</th>
                            </tr></thead>
                            <tbody>
                                <tr>
                                    <td class="b">Principal</td>
                                    @foreach($sc as $sz)<td class="tc">{{ $sz['trazoPrincipal'] ?? 0 }}</td>@endforeach
                                    <td class="tc b">{{ $totalPrincipal }}</td>
                                </tr>
                                <tr>
                                    <td class="b">Liquidación</td>
                                    @foreach($sc as $sz)<td class="tc">{{ $sz['liquidacion'] ?? 0 }}</td>@endforeach
                                    <td class="tc b">{{ $totalLiquidacion }}</td>
                                </tr>
                                <tr>
                                    <td class="b">Espiga</td>
                                    @foreach($sc as $sz)<td class="tc">{{ $sz['espiga'] ?? 0 }}</td>@endforeach
                                    <td class="tc b">{{ $totalEspiga }}</td>
                                </tr>
                            </tbody>
                        </table>
                    @endif
                </div>
                <div style="width:49%; display:inline-block; vertical-align:top; page-break-inside: avoid;">
                    {{-- Reporte de Unidades Reales --}}
                    @php
                        $colorCapas      = $additionalInfo['cutting']['colorCapas'] ?? [];
                        $sizeCurvesRep   = $additionalInfo['cutting']['sizeCurves'] ?? [];
                        $reportSizes     = !empty($sizeCurvesRep) ? $sizeCurvesRep : ($additionalInfo['cutting']['sizes'] ?? []);
                        $hasRealUnitData = !empty($colorCapas) && !empty($reportSizes);
                    @endphp
                    @if($hasRealUnitData)
                        <div class="sec-divider"></div>
                        <div class="sec-title g-sec">Reporte de Unidades Reales</div>
                        <div style="font-size:6.5pt;color:#6278a0;margin:0 0 3px;">Tela principal &mdash; agrupado por color</div>
                        <table class="dt">
                            <thead><tr>
                                <th class="g-th">Color</th>
                                @foreach($reportSizes as $sz)
                                    <th class="tc g-th">{{ $sz['size'] }}</th>
                                @endforeach
                                <th class="tc g-th">Total</th>
                            </tr></thead>
                            <tbody>
                                @php $colTotals = array_fill(0, count($reportSizes), 0); $grandTotal = 0; @endphp
                                @foreach($colorCapas as $cap)
                                    @php
                                        $rowUnits = []; $rowTotal = 0;
                                        foreach ($reportSizes as $sz) {
                                            $u = (int)(($sz['trazoPrincipal'] ?? 0) * $cap['layersFabric']
                                                     + ($sz['liquidacion']    ?? 0) * $cap['layersLiq']
                                                     + ($sz['espiga']         ?? 0) * $cap['layersHerringbone']);
                                            $rowUnits[] = $u; $rowTotal += $u;
                                        }
                                        $grandTotal += $rowTotal;
                                    @endphp
                                    <tr>
                                        <td class="b">{{ $cap['color'] }}</td>
                                        @foreach($rowUnits as $i => $u)
                                            @php $colTotals[$i] += $u; @endphp
                                            <td class="tc">{{ $u }}</td>
                                        @endforeach
                                        <td class="tc b">{{ $rowTotal }}</td>
                                    </tr>
                                @endforeach
                                <tr class="row-total">
                                    <td class="b">TOTAL</td>
                                    @foreach($colTotals as $ct)
                                        <td class="tc b">{{ $ct }}</td>
                                    @endforeach
                                    <td class="tc b" style="color:#b03030;">{{ $grandTotal }}</td>
                                </tr>
                            </tbody>
                        </table>
                    @elseif(!empty($producedColors) && !empty($additionalInfo['cutting']['sizes']))
                        <div class="sec-divider"></div>
                        <div class="sec-title g-sec">Reporte de Unidades Reales</div>
                        <div style="font-size:6.5pt;color:#6278a0;margin:0 0 3px;">Tela principal &mdash; agrupado por color</div>
                        <table class="dt">
                            <thead><tr>
                                <th class="g-th">Color</th>
                                @foreach($additionalInfo['cutting']['sizes'] as $sz)
                                    <th class="tc g-th">{{ $sz['size'] }}</th>
                                @endforeach
                                <th class="tc g-th">Total</th>
                            </tr></thead>
                            <tbody>
                                @foreach($producedColors as $colorName => $colorCode)
                                    <tr>
                                        <td class="b">{{ $colorName }}</td>
                                        @foreach($additionalInfo['cutting']['sizes'] as $sz)
                                            <td class="tc">-</td>
                                        @endforeach
                                        <td class="tc b">-</td>
                                    </tr>
                                @endforeach
                                <tr class="row-total">
                                    <td class="b">TOTAL</td>
                                    @foreach($additionalInfo['cutting']['sizes'] as $sz)
                                        <td class="tc b">-</td>
                                    @endforeach
                                    <td class="tc b">-</td>
                                </tr>
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>


            {{-- Hilos Asignados --}}
            @if(!empty($threads['lines']))
                @php
                    $threadsByColor = [];
                    foreach ($threads['lines'] as $th) {
                        $colorKey = trim($th['color'] ?? '');
                        if ($colorKey === '') continue;
                        $threadsByColor[$colorKey][] = $th;
                    }
                @endphp
                <div class="sec-divider"></div>
                <div class="sec-title p-sec">Hilos Asignados</div>
                <table class="dt" style="margin-bottom:4px;">
                    <thead><tr>
                        <th class="p-th tc" style="width:22px;padding:0;text-align:center;vertical-align:middle;">
                            <svg width="20" height="32" xmlns="http://www.w3.org/2000/svg">
                                <text x="10" y="16" transform="rotate(-90,10,16)" text-anchor="middle"
                                      font-size="7" font-weight="bold" fill="#ffffff" font-family="Arial, sans-serif">Color</text>
                            </svg>
                        </th>
                        <th class="p-th">Proceso</th>
                        <th class="p-th">Hilo</th>
                        <th class="tc p-th">Calibre</th>
                        <th class="p-th">Marca</th>
                        <th class="tc p-th">Puntada x Pulgada</th>
                        <th class="tc p-th">Código Color</th>
                    </tr></thead>
                    <tbody>
                        @foreach($threadsByColor as $colorName => $colorThreads)
                            @foreach($colorThreads as $thIdx => $th)
                                <tr>
                                    @if($thIdx === 0)
                                        @php
                                            $hexCode = $colorThreads[0]['colorCode'] ?? '';
                                            $label   = $colorName . ($hexCode ? ' ' . $hexCode : '');
                                            $svgH    = max(45, mb_strlen($label) * 5 + 10);
                                            $cx      = 10;
                                            $cy      = $svgH / 2;
                                        @endphp
                                        <td rowspan="{{ count($colorThreads) }}"
                                            style="width:22px;padding:0;text-align:center;vertical-align:middle;">
                                            <svg width="20" height="{{ $svgH }}" xmlns="http://www.w3.org/2000/svg">
                                                <text x="{{ $cx }}" y="{{ $cy }}"
                                                      transform="rotate(-90,{{ $cx }},{{ $cy }})"
                                                      text-anchor="middle"
                                                      font-size="7" font-weight="bold" fill="#1a3a5c"
                                                      font-family="Arial, sans-serif">{{ $label }}</text>
                                            </svg>
                                        </td>
                                    @endif
                                    <td class="b sm">{{ $th['processType'] ?? '-' }}</td>
                                    <td>{{ $th['threadName'] ?? '-' }}</td>
                                    <td class="tc">{{ $th['gauge'] ?? '-' }}</td>
                                    <td class="sm">{{ $th['brand'] ?? '-' }}</td>
                                    <td class="tc">{{ $th['stitchesPerInch'] ?? '-' }}</td>
                                    <td class="tc">{{ $th['colorCode'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            @endif
            
            {{-- Despiece --}}
            @if(!empty($additionalInfo['cutting']['pieces']))
                <div class="sec-divider"></div>
<div class="sec-title g-sec">Despiece de Piezas</div>
                <table class="dt">
                    <thead><tr>
                        <th class="tc g-th">#</th>
                        <th class="g-th">Pieza</th>
                        <th class="g-th">Tipo</th>
                        <th class="tc g-th">Cantidad</th>
                        <th class="tc g-th">Lado</th>
                        <th class="tc g-th">Grano</th>
                    </tr></thead>
                    <tbody>
                        @foreach($additionalInfo['cutting']['pieces'] as $idx => $pc)
                            <tr>
                                <td class="tc">{{ $idx+1 }}</td>
                                <td class="b">{{ $pc['name'] ?? '-' }}</td>
                                <td class="sm">{{ $pc['type'] ?? '-' }}</td>
                                <td class="tc">{{ $pc['quantity'] ?? '-' }}</td>
                                <td class="tc">{{ $pc['side'] ?? '-' }}</td>
                                <td class="tc">{{ $pc['grain'] ?? '-' }}°</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- Espiga / Liquidación --}}
            @if(!empty($additionalInfo['cutting']))
                @php $c = $additionalInfo['cutting']; @endphp
                @if(!empty($c['espigaLength']) || !empty($c['liqLength']))
                    <table class="info-cards" style="margin-top:4px;">
                        <tr>
                            <td><span class="ic-lbl">Espiga largo</span><span class="ic-val">{{ $c['espigaLength'] ?? '-' }}</span></td>
                            <td><span class="ic-lbl">Espiga curva</span><span class="ic-val">{{ $c['espigaCurve'] ?? '-' }}</span></td>
                            <td><span class="ic-lbl">Liquidación largo</span><span class="ic-val">{{ $c['liqLength'] ?? '-' }}</span></td>
                            <td><span class="ic-lbl">Liquidación curva</span><span class="ic-val">{{ $c['liqCurve'] ?? '-' }}</span></td>
                        </tr>
                    </table>
                @endif
                @if(!empty($c['generalNotes']))
                    <div class="note-box g-note" style="margin-top:4px;"><strong>Observaciones:</strong> {{ $c['generalNotes'] }}</div>
                @endif
            @endif



{{-- ╔══════════════════════════════════════════════════════════════╗ --}}
{{-- ║  PÁG. 3  ·  CONFECCIÓN                                      ║ --}}
{{-- ╚══════════════════════════════════════════════════════════════╝ --}}


            {{-- LISTADO OPERACIONAL --}}
            @if(!empty($operationalList['hasList']) && !empty($operationalList['list']['items']))
                <div class="sec-divider"></div>
<div class="sec-title p-sec">Listado Operacional de Confección</div>
                <table class="dt">
                    <thead><tr>
                        <th class="tc p-th" style="width:28px;">#</th>
                        <th class="p-th">Operación / Proceso</th>
                        <th class="p-th">Descripción</th>
                        <th class="tc p-th" style="width:45px;">Min.</th>
                    </tr></thead>
                    <tbody>
                        @php $tMin = 0; @endphp
                        @foreach($operationalList['list']['items'] as $item)
                            @php $tMin += (float)($item['minutes'] ?? 0); @endphp
                            <tr>
                                <td class="tc b">{{ $item['order'] ?? '-' }}</td>
                                <td class="b">{{ $item['process'] ?? '-' }}</td>
                                <td class="sm">{{ $item['description'] ?? '' }}</td>
                                <td class="tc b">{{ $item['minutes'] !== null ? num($item['minutes']) : '-' }}</td>
                            </tr>
                        @endforeach
                        <tr class="row-total p-tot">
                            <td colspan="3" class="tr b">Total minutos de ruta:</td>
                            <td class="tc b">{{ num($tMin) }}</td>
                        </tr>
                    </tbody>
                </table>
            @endif

            {{-- Colores × Tallas --}}
            @if(!empty($producedColors) && !empty($additionalInfo['cutting']['sizes']))
                <div class="sec-divider"></div>
<div class="sec-title p-sec">Distribución Colores × Tallas</div>
                <table class="dt">
                    <thead>
                        <tr>
                            <th class="p-th">Color</th>
                            @foreach($additionalInfo['cutting']['sizes'] as $sz)
                                <th class="tc p-th">{{ $sz['size'] ?? '-' }}</th>
                            @endforeach
                            <th class="tc p-th b">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($producedColors as $cN => $cC)
                            <tr>
                                <td class="b">{{ $cN }}</td>
                                @foreach($additionalInfo['cutting']['sizes'] as $sz)
                                    <td class="tc">{{ $sz['units'] ?? '-' }}</td>
                                @endforeach
                                <td class="tc b">-</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- Insumos Confección --}}
            @if(!empty($suppliesConf) || !empty($suppliesOther))
                <div class="sec-divider"></div>
<div class="sec-title p-sec">Insumos</div>
                <table class="dt">
                    <thead><tr>
                        <th class="p-th" style="width:18%;">Tipo de insumo</th>
                        <th class="p-th" style="width:37%;">Insumo</th>
                        <th class="tc p-th" style="width:12%;">Moda/Línea</th>
                        <th class="tc p-th" style="width:8%;">Cantidad</th>
                        <th class="tc p-th" style="width:25%;">Imagen</th>
                    </tr></thead>
                    <tbody>
                        @foreach(array_merge($suppliesConf, $suppliesOther) as $s)
                            @php
                                $stageLabel = match(strtoupper(trim($s['stage'] ?? ''))) {
                                    'CONFECCION' => 'Confección',
                                    'TERMINACION' => 'Terminación',
                                    'CORTE' => 'Corte',
                                    'GENERAL' => 'General',
                                    default => ucfirst(strtolower($s['stage'] ?? '-')),
                                };
                                $modaLineaLabel = match(strtoupper(trim($s['modaLinea'] ?? ''))) {
                                    'MODA'  => 'Moda',
                                    'LINEA' => 'Línea',
                                    default => '-',
                                };
                            @endphp
                            <tr>
                                <td class="b">{{ $stageLabel }}</td>
                                <td class="b">{{ $s['displayLabel'] ?? $s['name'] ?? '-' }}</td>
                                <td class="tc b">{{ $modaLineaLabel }}</td>
                                <td class="tc b">{{ ($s['qtyPerUnit']??0)>0 ? (int)round((float)$s['qtyPerUnit']) : '-' }}</td>
                                <td class="tc">
                                    @if(!empty($s['documentUrl']))
                                        <img src="{{ $s['documentUrl'] }}" style="max-height:70px; max-width:100px; object-fit:contain;" />
                                    @else
                                        <span class="mut xs">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- Tabla Medidas de Insumos de Confección --}}
            @php
                $confSupplyIds = array_column(array_merge($suppliesConf, $suppliesOther), 'id');
                $confMeasRows = [];
                foreach ($sizeMeasurementRows as $s) {
                    if (in_array($s['id'] ?? null, $confSupplyIds) || in_array($s, array_merge($suppliesConf, $suppliesOther))) {
                        $confMeasRows[] = $s;
                    }
                }
                // Derivar tallas desde los datos de medidas si $sizesDisplay está vacío
                $measSizes = $sizesDisplay;
                if (empty($measSizes) && !empty($confMeasRows)) {
                    $measSizes = array_keys((array)($confMeasRows[0]['sizeMeasurements'] ?? []));
                }
            @endphp
            @if(!empty($confMeasRows) && !empty($measSizes))
                <div class="sec-divider"></div>
<div class="sec-title p-sec">Tabla de Medidas — Insumos de Confección</div>
                <table class="dt">
                    <thead>
                        <tr>
                            <th class="p-th">Insumo</th>
                            <th class="p-th">Medida</th>
                            @foreach($measSizes as $sz)<th class="tc p-th">{{ $sz }}</th>@endforeach
                            <th class="tc p-th">Promedio Medida de Corte</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($confMeasRows as $s)
                            @php
                                $ms = $s['sizeMeasurements'];
                                $corteVals = [];
                                foreach ($measSizes as $sz) {
                                    if (isset($ms[$sz]['corte']) && is_numeric($ms[$sz]['corte'])) {
                                        $corteVals[] = (float)$ms[$sz]['corte'];
                                    }
                                }
                                $promCorte = count($corteVals) > 0 ? array_sum($corteVals) / count($corteVals) : null;
                            @endphp
                            <tr>
                                <td rowspan="2" class="b" style="vertical-align:middle;">{{ $s['displayLabel'] ?? $s['name'] ?? '-' }}</td>
                                <td class="xs b">Medida Corte</td>
                                @foreach($measSizes as $sz)
                                    <td class="tc">{{ isset($ms[$sz]['corte']) ? num($ms[$sz]['corte']) : '-' }}</td>
                                @endforeach
                                <td class="tc b">{{ $promCorte !== null ? num($promCorte) : '-' }}</td>
                            </tr>
                            <tr>
                                <td class="xs b">Medida Terminación</td>
                                @foreach($measSizes as $sz)
                                    <td class="tc">{{ isset($ms[$sz]['termi']) ? num($ms[$sz]['termi']) : '-' }}</td>
                                @endforeach
                                <td></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif




{{-- ╔══════════════════════════════════════════════════════════════╗ --}}
{{-- ║  PÁG. 4  ·  TERMINACIÓN / ESTAMPADO / LOGÍSTICA             ║ --}}
{{-- ╚══════════════════════════════════════════════════════════════╝ --}}


<table class="layout">
    <tr>
        {{-- ─ IZQUIERDA 50%: Insumos term + Medidas + Estampado ─ --}}
        <td class="col-data" style="width:50%;">

            {{-- Estampado --}}
            @if(!empty($additionalInfo['stamping']))
                @php
                    $stamp = $additionalInfo['stamping'];
                    $stampProcesos = null;
                    $stampObservacion = null;
                    if (!empty($stamp['designNotes'])) {
                        $raw = $stamp['designNotes'];
                        if (preg_match('/PROCESOS:\s*(.*?)\s*OBSERVACI[OÓ]N:\s*(.*)/si', $raw, $m)) {
                            $stampProcesos    = trim($m[1]) ?: null;
                            $stampObservacion = trim($m[2]) ?: null;
                        } else {
                            $stampObservacion = trim($raw) ?: null;
                        }
                    }
                @endphp
                <div class="sec-divider"></div>
<div class="sec-title a-sec">Estampado</div>
                @if($stampProcesos)
                    <div class="note-box a-note"><strong>Procesos:</strong> {{ $stampProcesos }}</div>
                @endif
                @if($stampObservacion)
                    <div class="note-box a-note"><strong>Observación:</strong> {{ $stampObservacion }}</div>
                @endif
                @if(!empty($stamp['attachments']))
                    <div class="sec-sub a-sub" style="margin-top:4px;">Imágenes de Estampado</div>
                    <div style="text-align:center; margin-bottom:5px;">
                        @foreach($stamp['attachments'] as $att)
                            @if(!empty($att['url']))<img src="{{ $att['url'] }}" alt="{{ $att['name'] ?? 'Estampado' }}" style="max-height:120px; max-width:160px; border:1px solid #dde5f0; margin:3px; object-fit:contain;" />@endif
                        @endforeach
                    </div>
                @endif
                @if(!empty($stamp['drawingAttachments']))
                    <div class="sec-sub a-sub" style="margin-top:4px;">Dibujos Técnicos de Estampado</div>
                    <div style="text-align:center; margin-bottom:5px;">
                        @foreach($stamp['drawingAttachments'] as $att)
                            @if(!empty($att['url']))<img src="{{ $att['url'] }}" alt="{{ $att['name'] ?? 'Dibujo' }}" style="max-height:120px; max-width:160px; border:1px solid #dde5f0; margin:3px; object-fit:contain;" />@endif
                        @endforeach
                    </div>
                @endif
            @endif

        </td>

        {{-- ─ DERECHA 50%: Lavandería ─ --}}
        <td style="width:50%; vertical-align:top;">

            {{-- Lavandería --}}
            @if(!empty($additionalInfo['laundry']))
                @php $laundry = $additionalInfo['laundry']; @endphp
                <div class="sec-divider"></div>
<div class="sec-title a-sec">Lavandería</div>
                @if(!empty($laundry['type']))
                    <div class="note-box a-note"><strong>Tipo:</strong> {{ $laundry['type'] }}</div>
                @endif
                @if(!empty($laundry['instructions']))
                    <div class="note-box a-note"><strong>Observación:</strong> {{ $laundry['instructions'] }}</div>
                @endif
                @if(!empty($laundry['attachments']))
                    <div class="sec-sub a-sub" style="margin-top:4px;">Imagen de Lavandería</div>
                    <div style="text-align:center; margin-bottom:5px;">
                        @foreach($laundry['attachments'] as $att)
                            @if(!empty($att['url']))<img src="{{ $att['url'] }}" alt="{{ $att['name'] ?? 'Lavandería' }}" style="max-height:120px; max-width:160px; border:1px solid #dde5f0; margin:3px; object-fit:contain;" />@endif
                        @endforeach
                    </div>
                @endif
            @endif

        </td>
    </tr>
</table>

</div>

</body>
</html>
