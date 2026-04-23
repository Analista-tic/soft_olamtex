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
@page { size: letter landscape; margin: 6mm 7mm 6mm 7mm; }
* { margin:0; padding:0; box-sizing:border-box; }
html, body {
    font-family: Arial, Helvetica, sans-serif;
    background: #fff;
    color: #1e2533;
    font-size: 9pt;
    line-height: 1.35;
}

/* ════════════════════════════════════════════════════════════
   HEADER BAND
   ════════════════════════════════════════════════════════════ */
.hdr { width:100%; border-collapse:collapse; margin-bottom:0; }
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
.hdr-meta { width:165px; text-align:right; font-size:8.5pt; color:rgba(255,255,255,.8); }
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
.info-cards { width:100%; border-collapse:collapse; margin-bottom:6px; }
.info-cards td {
    padding:5px 8px; border:1px solid #dde5f0;
    background:#f6f9ff; vertical-align:top;
}
.ic-lbl {
    display:block; font-size:7pt; font-weight:700;
    text-transform:uppercase; letter-spacing:.4px;
    color:#6278a0; margin-bottom:2px;
}
.ic-val { font-size:9.5pt; font-weight:600; color:#1e2533; }

/* ════════════════════════════════════════════════════════════
   SECTION TITLES
   ════════════════════════════════════════════════════════════ */
.sec-title {
    font-size:9pt; font-weight:700; text-transform:uppercase;
    letter-spacing:.6px; padding:5px 9px;
    margin:6px 0 3px 0; border-left:4px solid;
}
.sec-sub {
    font-size:9pt; font-weight:700;
    padding:3px 0 2px 0; margin:5px 0 3px 0;
    border-bottom:2px solid;
}

/* ════════════════════════════════════════════════════════════
   DATA TABLES
   ════════════════════════════════════════════════════════════ */
table.dt { width:100%; border-collapse:collapse; margin-bottom:5px; }
table.dt th {
    font-size:8pt; font-weight:700; text-transform:uppercase;
    letter-spacing:.3px; padding:3px 5px;
    text-align:left; white-space:nowrap;
    border:.5px solid;
}
table.dt td {
    font-size:8.5pt; padding:2.8px 5px;
    border:.5px solid #dde5f0; vertical-align:top;
}
table.dt tbody tr:nth-child(even) { background:#f4f8ff; }
table.dt .row-total { font-weight:700; }
table.dt .row-stage td {
    font-weight:700; font-size:8pt;
    text-transform:uppercase; letter-spacing:.3px; padding:3.5px 6px;
}

/* ════════════════════════════════════════════════════════════
   DRAWING  (right column)
   ════════════════════════════════════════════════════════════ */
.draw-wrap { margin-bottom:6px; }
.draw-lbl {
    font-size:8pt; font-weight:700; text-transform:uppercase;
    letter-spacing:.5px; color:#fff; padding:3px 7px;
}
.draw-box {
    border:1.5px solid; padding:6px;
    background:#f8fbff; text-align:center;
}
.draw-box img { max-width:100%; display:block; margin:0 auto; object-fit:contain; }
.draw-empty {
    border:2px dashed #b8cce4; background:#f4f7ff;
    text-align:center; padding:0;
}
.draw-empty-inner { font-size:9pt; color:#8a9bbf; }

/* ════════════════════════════════════════════════════════════
   COLORS GRID
   ════════════════════════════════════════════════════════════ */
.clr-lbl {
    font-size:8pt; font-weight:700; text-transform:uppercase;
    letter-spacing:.4px; color:#fff; padding:3px 7px;
    margin-top:3px;
}
.clr-grid { width:100%; border-collapse:collapse; margin-bottom:5px; }
.clr-grid td {
    padding:4px 6px; text-align:center; vertical-align:middle;
    border:.5px solid #dde5f0; background:#f6f9ff;
}
.clr-name { font-size:8.5pt; font-weight:700; color:#1e2533; }
.clr-code { font-size:7.5pt; font-weight:600; margin-top:1px; }

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
    font-size:8.5pt; border-left:4px solid;
}
.warn-box {
    padding:4px 8px; margin-bottom:4px; font-size:8.5pt;
    border-left:4px solid #d48a0a; background:#fffbec; color:#6e4a00;
}

/* ════════════════════════════════════════════════════════════
   UTILITIES
   ════════════════════════════════════════════════════════════ */
.tr  { text-align:right; }
.tc  { text-align:center; }
.b   { font-weight:700; }
.nw  { white-space:nowrap; }
.sm  { font-size:8pt; }
.xs  { font-size:7.5pt; }
.mut { color:#7a8fa8; }
.page-break { page-break-before:always; }
.page-frame { padding: 2mm 2.4mm 1.6mm 2.4mm; }

/* ════════════════════════════════════════════════════════════
   PAGE FOOTER
   ════════════════════════════════════════════════════════════ */
.pg-footer {
    font-size:8pt; color:#fff; text-align:center;
    padding:3px 0; margin-top:4px;
}

/* ════════════════════════════════════════════════════════════
    PER-PAGE COLOR TOKENS (intensidad media)
    Precosteo  → Navy   #3a618d / accent #84a7cc / light #f1f5fa
    Corte      → Green  #367252 / accent #6db28a / light #f0f7f3
    Confección → Purple #654787 / accent #a688c7 / light #f6f1fa
    Terminación→ Amber  #94653a / accent #d8a56b / light #fef7ee
    ════════════════════════════════════════════════════════════ */

/* — NAVY — */
.n-hdr  { background:#3a618d; }
.n-bar  { background:#84a7cc; }
.n-sec  { background:#f1f5fa; border-left-color:#84a7cc; color:#34597f; }
.n-sub  { color:#34597f; border-bottom-color:#84a7cc; }
.n-th   { background:#e2ecf8; color:#34597f; border-color:#c8d9ee; }
.n-draw { border-color:#c8d9ee; }
.n-dlbl { background:#3a618d; }
.n-clbl { background:#3a618d; }
.n-code { color:#84a7cc; }
.n-stg td { background:#e2ecf8 !important; color:#34597f; }
.n-tot td { background:#e2ecf8 !important; font-weight:700; }
.n-foot { background:#3a618d; }
.n-note { background:#f3f7fc; border-left-color:#84a7cc; color:#2e4e72; }

/* — GREEN — */
.g-hdr  { background:#367252; }
.g-bar  { background:#6db28a; }
.g-sec  { background:#f0f7f3; border-left-color:#6db28a; color:#2f694b; }
.g-sub  { color:#2f694b; border-bottom-color:#6db28a; }
.g-th   { background:#e0f1e8; color:#2f694b; border-color:#c1dfce; }
.g-draw { border-color:#c1dfce; }
.g-dlbl { background:#367252; }
.g-clbl { background:#367252; }
.g-code { color:#6db28a; }
.g-stg td { background:#e0f1e8 !important; color:#2f694b; }
.g-tot td { background:#e0f1e8 !important; font-weight:700; }
.g-foot { background:#367252; }
.g-note { background:#f4faf7; border-left-color:#6db28a; color:#285a41; }

/* — PURPLE — */
.p-hdr  { background:#654787; }
.p-bar  { background:#a688c7; }
.p-sec  { background:#f6f1fa; border-left-color:#a688c7; color:#5b4179; }
.p-sub  { color:#5b4179; border-bottom-color:#a688c7; }
.p-th   { background:#ece3f4; color:#5b4179; border-color:#d8c7ea; }
.p-draw { border-color:#d8c7ea; }
.p-dlbl { background:#654787; }
.p-clbl { background:#654787; }
.p-code { color:#a688c7; }
.p-stg td { background:#ece3f4 !important; color:#5b4179; }
.p-tot td { background:#ece3f4 !important; font-weight:700; }
.p-foot { background:#654787; }
.p-note { background:#f8f4fb; border-left-color:#a688c7; color:#4f3869; }

/* — AMBER — */
.a-hdr  { background:#94653a; }
.a-bar  { background:#d8a56b; }
.a-sec  { background:#fef7ee; border-left-color:#d8a56b; color:#855a33; }
.a-sub  { color:#855a33; border-bottom-color:#d8a56b; }
.a-th   { background:#f7e9d1; color:#855a33; border-color:#e8d0ac; }
.a-draw { border-color:#e8d0ac; }
.a-dlbl { background:#94653a; }
.a-clbl { background:#94653a; }
.a-code { color:#d8a56b; }
.a-stg td { background:#f7e9d1 !important; color:#855a33; }
.a-tot td { background:#f7e9d1 !important; font-weight:700; }
.a-foot { background:#94653a; }
.a-note { background:#fef9f1; border-left-color:#d8a56b; color:#6f4d2b; }

/* — COMPACCIÓN LOCAL PÁG. 3 y 4 — */
.p-page .info-cards,
.a-page .info-cards { margin-bottom:4px; }

.p-page .sec-title,
.a-page .sec-title {
    padding:4px 8px;
    margin:4px 0 2px 0;
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
{{-- ║  PÁG. 1  ·  PRECOSTEO                                       ║ --}}
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
            <h1>Ficha Técnica · Precosteo</h1>
            <div class="sub">{{ $basic['opCode'] ?? '-' }} &nbsp;—&nbsp; {{ $reference ?? '-' }}</div>
        </td>
        <td class="hdr-meta">
            <strong>{{ $printDate }}</strong>
            {{ $basic['reference'] ?? '-' }}
        </td>
    </tr>
</table>
<div class="hdr-bar n-bar"></div>

{{-- ── INFO BÁSICA ── --}}
<table class="info-cards">
    <tr>
        <td style="width:14%;"><span class="ic-lbl">Orden producción</span><span class="ic-val b">{{ $basic['opCode'] ?? '-' }}</span></td>
        <td style="width:18%;"><span class="ic-lbl">Referencia</span><span class="ic-val">{{ $basic['reference'] ?? '-' }}</span></td>
        <td style="width:10%;"><span class="ic-lbl">Fecha</span><span class="ic-val">{{ $basic['date'] ?? '-' }}</span></td>
        <td style="width:10%;"><span class="ic-lbl">Destino</span><span class="ic-val">{{ $basic['destination'] ?? '-' }}</span></td>
        <td style="width:10%;"><span class="ic-lbl">Sección</span><span class="ic-val">{{ $basic['section'] ?? '-' }}</span></td>
        <td style="width:10%;"><span class="ic-lbl">Grupo</span><span class="ic-val">{{ $basic['group'] ?? '-' }}</span></td>
        <td style="width:10%;"><span class="ic-lbl">Marca</span><span class="ic-val">{{ $basic['brand'] ?? '-' }}</span></td>
        <td style="width:8%;"><span class="ic-lbl">Mundo</span><span class="ic-val">{{ $basic['world'] ?? '-' }}</span></td>
        <td style="width:10%;"><span class="ic-lbl">Temporada</span><span class="ic-val">{{ $basic['season'] ?? '-' }}</span></td>
    </tr>
    <tr>
        <td><span class="ic-lbl">Prioridad</span><span class="ic-val">{{ $basic['priority'] ?? '-' }}</span></td>
        <td><span class="ic-lbl">Base tela</span><span class="ic-val">{{ $basic['base'] ?? '-' }}</span></td>
        <td><span class="ic-lbl">Base hilaza</span><span class="ic-val">{{ $basic['baseYarn'] ?? '-' }}</span></td>
        <td><span class="ic-lbl">Precio venta</span><span class="ic-val b">{{ $basic['priceSale'] !== null ? money($basic['priceSale']) : '-' }}</span></td>
        <td><span class="ic-lbl">Precio facturación</span><span class="ic-val b">{{ $basic['priceBilling'] !== null ? money($basic['priceBilling']) : '-' }}</span></td>
        <td><span class="ic-lbl">Unidades</span><span class="ic-val b">{{ $basic['units'] ?? '-' }}</span></td>
        <td colspan="2"><span class="ic-lbl">Descripción</span><span class="ic-val sm">{{ $basic['description'] ?? '-' }}</span></td>
        <td><span class="ic-lbl">E-commerce</span><span class="ic-val">{{ !empty($basic['ecommerce']) ? 'Sí' : 'No' }}</span></td>
    </tr>
</table>

{{-- ── CUERPO: 2 COLUMNAS ── --}}
<table class="layout">
    <tr>
        {{-- ─ IZQUIERDA 58%: Materia Prima + Procesos + Costos ─ --}}
        <td class="col-data" style="width:58%;">

            {{-- Tallas / Curva --}}
            @if(!empty($additionalInfo['cutting']['sizes']))
                <div class="sec-title n-sec">Tallas / Curva / Unidades</div>
                <table class="dt">
                    <thead><tr>
                        <th class="n-th">Talla</th>
                        <th class="tc n-th">Curva</th>
                        <th class="tc n-th">Unidades</th>
                        <th class="n-th">Talla</th>
                        <th class="tc n-th">Curva</th>
                        <th class="tc n-th">Unidades</th>
                        <th class="n-th">Talla</th>
                        <th class="tc n-th">Curva</th>
                        <th class="tc n-th">Unidades</th>
                    </tr></thead>
                    <tbody>
                        @php
                            $szRows = $additionalInfo['cutting']['sizes'];
                            $szChunks = array_chunk($szRows, 3);
                            $totalUnitsP1 = array_sum(array_column($szRows, 'units'));
                        @endphp
                        @foreach($szChunks as $chunk)
                            <tr>
                                @foreach($chunk as $sz)
                                    <td class="b">{{ $sz['size'] ?? '-' }}</td>
                                    <td class="tc">{{ $sz['curve'] ?? '-' }}</td>
                                    <td class="tc">{{ $sz['units'] ?? '-' }}</td>
                                @endforeach
                                @for($pad = count($chunk); $pad < 3; $pad++)
                                    <td></td><td></td><td></td>
                                @endfor
                            </tr>
                        @endforeach
                        <tr class="row-total n-tot">
                            <td colspan="8" class="tr b">TOTAL UNIDADES:</td>
                            <td class="tc b">{{ $totalUnitsP1 }}</td>
                        </tr>
                    </tbody>
                </table>
            @elseif(!empty($sizesDisplay))
                <div class="sec-title n-sec">Tallas</div>
                <p style="font-size:9pt; padding:4px 0;">{{ implode(' · ', $sizesDisplay) }}</p>
            @endif

            {{-- Materia Prima --}}
            @if(!empty($rawMaterials))
                <div class="sec-title n-sec">Materia Prima</div>
                <table class="dt">
                    <thead><tr>
                        <th class="n-th">M.P.</th>
                        <th class="n-th">Tela / Artículo</th>
                        <th class="n-th">Proveedor</th>
                        <th class="tc n-th">Ancho</th>
                        <th class="tc n-th">Prom. Trazo</th>
                        <th class="tc n-th">Consumo</th>
                        <th class="tr n-th">Precio Tela</th>
                        <th class="tr n-th">Costo Prenda</th>
                    </tr></thead>
                    <tbody>
                        @foreach($rawMaterials as $idx => $row)
                            @php $cp = (($row['consumo'] ?? 0) > 0 && ($row['lastUnitPriceCop'] ?? 0) > 0) ? (float)$row['consumo'] * (float)$row['lastUnitPriceCop'] : 0; @endphp
                            <tr>
                                <td class="b nw">Tela {{ $idx + 1 }}</td>
                                <td>{{ $row['tela'] ?? '-' }}</td>
                                <td class="sm">{{ $row['lastSupplierName'] ?? '-' }}</td>
                                <td class="tc">{{ isset($row['ancho']) ? num($row['ancho']) : '-' }}</td>
                                <td class="tc">{{ isset($row['promTrazo']) ? num($row['promTrazo'],3) : '-' }}</td>
                                <td class="tc b">{{ isset($row['consumo']) ? num($row['consumo'],3) : '-' }}</td>
                                <td class="tr nw">{{ ($row['lastUnitPriceCop'] ?? 0) > 0 ? money($row['lastUnitPriceCop']) : '-' }}</td>
                                <td class="tr nw b">{{ $cp > 0 ? money($cp) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- Procesos + Costos en 2 columnas --}}
            <table class="layout" style="margin-top:2px;">
                <tr>
                    {{-- Procesos --}}
                    <td style="width:50%; padding-right:7px; vertical-align:top;">
                        @if(!empty($processes['processes']))
                            <div class="sec-title n-sec">Procesos</div>
                            <table class="dt">
                                <thead><tr>
                                    <th class="n-th">Proceso</th>
                                    <th class="n-th">Tipo</th>
                                    <th class="tr n-th">Precio</th>
                                </tr></thead>
                                <tbody>
                                    @foreach($processes['processes'] as $proc)
                                        <tr>
                                            <td class="b nw">P{{ $proc['order'] ?? '-' }}</td>
                                            <td>{{ $proc['type'] ?? '-' }}</td>
                                            <td class="tr nw">{{ isset($proc['manualPrice']) && $proc['manualPrice'] !== null ? money($proc['manualPrice']) : (isset($proc['fixedCost']) ? money($proc['fixedCost']) : '-') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </td>
                    {{-- Resumen Costos --}}
                    <td style="width:50%; vertical-align:top;">
                        @if(!empty($costs))
                            <div class="sec-title n-sec">Resumen de Costos</div>
                            <table class="cost-cards">
                                <tr>
                                    <td><span class="ic-lbl">Costo total</span><span class="ic-val b">{{ money($costs['costs']['total'] ?? 0) }}</span></td>
                                    <td><span class="ic-lbl">Facturación</span><span class="ic-val b">{{ $costs['prices']['invoicing'] !== null ? money($costs['prices']['invoicing']) : '-' }}</span></td>
                                </tr>
                                <tr>
                                    <td><span class="ic-lbl">Precio venta</span><span class="ic-val b">{{ $basic['priceSale'] !== null ? money($basic['priceSale']) : '-' }}</span></td>
                                    <td>
                                        <span class="ic-lbl">Rentabilidad</span>
                                        @if($costs['profitability']['olamtex']['value'] !== null)
                                            <span class="ic-val {{ ($costs['profitability']['olamtex']['value'] ?? 0) >= 0 ? 'cost-pos' : 'cost-neg' }}">
                                                {{ money($costs['profitability']['olamtex']['value']) }}
                                                ({{ num($costs['profitability']['olamtex']['percent'] ?? 0, 2) }}%)
                                            </span>
                                        @else
                                            <span class="ic-val">-</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                            <table class="dt">
                                <thead><tr><th class="n-th">Concepto</th><th class="tr n-th">Valor</th></tr></thead>
                                <tbody>
                                    <tr><td>Materia prima</td><td class="tr nw">{{ money($costs['costs']['rawMaterial'] ?? 0) }}</td></tr>
                                    <tr><td>MP / unidad</td><td class="tr nw">{{ money($costs['costs']['rawMaterialPerUnit'] ?? 0) }}</td></tr>
                                    <tr><td>Insumos</td><td class="tr nw">{{ money($costs['costs']['supplies'] ?? 0) }}</td></tr>
                                    <tr><td>Corte</td><td class="tr nw">{{ money($costs['costs']['cut'] ?? 0) }}</td></tr>
                                    <tr><td>Confección</td><td class="tr nw">{{ money($costs['costs']['confection'] ?? 0) }}</td></tr>
                                    <tr><td>Terminación</td><td class="tr nw">{{ money($costs['costs']['finishing'] ?? 0) }}</td></tr>
                                    <tr class="row-total n-tot"><td class="b">TOTAL</td><td class="tr nw b">{{ money($costs['costs']['total'] ?? 0) }}</td></tr>
                                </tbody>
                            </table>
                        @endif
                    </td>
                </tr>
            </table>

        </td>

        {{-- ─ DERECHA 42%: Dibujo grande + Colores ─ --}}
        <td style="width:42%; vertical-align:top;">
            <div class="draw-wrap">
                <div class="draw-lbl n-dlbl">Dibujo Plano de Referencia</div>
                @if(!empty($drawingBase64))
                    <div class="draw-box n-draw">
                        <img src="{{ $drawingBase64 }}" alt="Dibujo plano" style="max-height:360px;" />
                    </div>
                @else
                    <div class="draw-empty n-draw" style="height:360px;">
                        <div class="draw-empty-inner" style="padding-top:160px;">Sin dibujo plano</div>
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
                            <div class="clr-name">{{ $cN }}</div>
                            @if($cC)<div class="clr-code n-code">{{ $cC }}</div>@endif
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

<div class="pg-footer n-foot">Ficha Técnica &nbsp;·&nbsp; Olamtex S.A.S. &nbsp;·&nbsp; Generada: {{ $printDate }} &nbsp;·&nbsp; Pág. 1 — PRECOSTEO</div>
</div>


{{-- ╔══════════════════════════════════════════════════════════════╗ --}}
{{-- ║  PÁG. 2  ·  CORTE Y NUMERACIÓN                              ║ --}}
{{-- ╚══════════════════════════════════════════════════════════════╝ --}}
<div class="page-break"></div>
<div class="page-frame">

<table class="hdr g-hdr">
    <tr>
        <td class="hdr-logo">
            @if(file_exists(public_path('images/olamtex.png')))<img src="{{ public_path('images/olamtex.png') }}" alt="Olamtex" />@else<span class="logo-txt">OLAMTEX</span>@endif
        </td>
        <td class="hdr-center">
            <h1>Ficha Técnica · Corte y Numeración</h1>
            <div class="sub">{{ $basic['opCode'] ?? '-' }} &nbsp;—&nbsp; {{ $reference ?? '-' }}</div>
        </td>
        <td class="hdr-meta">
            <strong>{{ $printDate }}</strong>
            OP: {{ $basic['opCode'] ?? '-' }}
        </td>
    </tr>
</table>
<div class="hdr-bar g-bar"></div>

<table class="layout">
    <tr>
        {{-- ─ IZQUIERDA 43%: info + MP + rollos + notas ─ --}}
        <td class="col-data" style="width:43%;">

            {{-- Mini info --}}
            <table class="info-cards">
                <tr>
                    <td style="width:22%;"><span class="ic-lbl">Orden prod.</span><span class="ic-val b">{{ $basic['opCode'] ?? '-' }}</span></td>
                    <td style="width:22%;"><span class="ic-lbl">Grupo</span><span class="ic-val">{{ $basic['group'] ?? '-' }}</span></td>
                    <td style="width:22%;"><span class="ic-lbl">Marca</span><span class="ic-val">{{ $basic['brand'] ?? '-' }}</span></td>
                    <td style="width:17%;"><span class="ic-lbl">Temporada</span><span class="ic-val">{{ $basic['season'] ?? '-' }}</span></td>
                    <td style="width:17%;"><span class="ic-lbl">Precio corte</span><span class="ic-val b">{{ !empty($processes['params']['cuttingCost']) ? money($processes['params']['cuttingCost']) : '-' }}</span></td>
                </tr>
            </table>

            {{-- Tallas / Curva --}}
            @if(!empty($additionalInfo['cutting']['sizes']))
                <div class="sec-title g-sec">Tallas · Curva · Unidades</div>
                <table class="dt">
                    <thead><tr>
                        <th class="g-th">Talla</th>
                        <th class="tc g-th">Curva</th>
                        <th class="tc g-th">Uds.</th>
                        <th class="g-th">Talla</th>
                        <th class="tc g-th">Curva</th>
                        <th class="tc g-th">Uds.</th>
                    </tr></thead>
                    <tbody>
                        @php
                            $szArr = $additionalInfo['cutting']['sizes'];
                            $szPairs = array_chunk($szArr, 2);
                            $totalU = array_sum(array_column($szArr, 'units'));
                        @endphp
                        @foreach($szPairs as $pair)
                            <tr>
                                @foreach($pair as $sz)
                                    <td class="b">{{ $sz['size'] ?? '-' }}</td>
                                    <td class="tc">{{ $sz['curve'] ?? '-' }}</td>
                                    <td class="tc b">{{ $sz['units'] ?? '-' }}</td>
                                @endforeach
                                @if(count($pair) < 2)<td></td><td></td><td></td>@endif
                            </tr>
                        @endforeach
                        <tr class="row-total g-tot">
                            <td colspan="5" class="tr b">TOTAL:</td>
                            <td class="tc b">{{ $totalU }}</td>
                        </tr>
                    </tbody>
                </table>
            @endif

            {{-- Materia Prima --}}
            @if(!empty($rawMaterials))
                <div class="sec-title g-sec">Materia Prima</div>
                <table class="dt">
                    <thead><tr>
                        <th class="g-th">M.P.</th>
                        <th class="g-th">Tela</th>
                        <th class="tc g-th">Ancho</th>
                        <th class="tc g-th">P.Trazo</th>
                        <th class="tc g-th">L.Trazo</th>
                        <th class="tc g-th">L.Liq.</th>
                        <th class="tc g-th">Consumo</th>
                        <th class="tr g-th">Precio</th>
                    </tr></thead>
                    <tbody>
                        @foreach($rawMaterials as $idx => $row)
                            <tr>
                                <td class="b nw">Tela {{ $idx+1 }}</td>
                                <td class="sm">{{ $row['tela'] ?? '-' }}</td>
                                <td class="tc">{{ isset($row['ancho']) ? num($row['ancho']).' '.($row['anchoUnit']??'') : '-' }}</td>
                                <td class="tc">{{ isset($row['promTrazo']) ? num($row['promTrazo'],3) : '-' }}</td>
                                <td class="tc">{{ isset($row['largoTrazo']) ? num($row['largoTrazo'],3) : '-' }}</td>
                                <td class="tc">{{ isset($row['largoLiq']) ? num($row['largoLiq'],3) : '-' }}</td>
                                <td class="tc b">{{ isset($row['consumo']) ? num($row['consumo'],3) : '-' }}</td>
                                <td class="tr nw">{{ ($row['lastUnitPriceCop']??0)>0 ? money($row['lastUnitPriceCop']) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- Despiece --}}
            @if(!empty($additionalInfo['cutting']['pieces']))
                <div class="sec-title g-sec">Despiece de Piezas</div>
                <table class="dt">
                    <thead><tr>
                        <th class="tc g-th">#</th>
                        <th class="g-th">Pieza</th>
                        <th class="g-th">Tipo</th>
                        <th class="tc g-th">Cant.</th>
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

        </td>

        {{-- ─ DERECHA 57%: Dibujo MUY GRANDE + colores + rollos ─ --}}
        <td style="width:57%; vertical-align:top;">

            <div class="draw-wrap">
                <div class="draw-lbl g-dlbl">Dibujo Plano de Referencia</div>
                @if(!empty($drawingBase64))
                    <div class="draw-box g-draw">
                        <img src="{{ $drawingBase64 }}" alt="Dibujo plano" style="max-height:400px;" />
                    </div>
                @else
                    <div class="draw-empty g-draw" style="height:400px;">
                        <div class="draw-empty-inner" style="padding-top:185px;">Sin dibujo plano</div>
                    </div>
                @endif
            </div>

            @if(!empty($producedColors))
                <div class="clr-lbl g-clbl">Colores</div>
                <table class="clr-grid">
                    @php $ci=0; @endphp
                    @foreach($producedColors as $cN => $cC)
                        @if($ci % 4 === 0)<tr>@endif
                        <td>
                            <div class="clr-name">{{ $cN }}</div>
                            @if($cC)<div class="clr-code g-code">{{ $cC }}</div>@endif
                        </td>
                        @php $ci++; @endphp
                        @if($ci % 4 === 0)</tr>@endif
                    @endforeach
                    @if($ci % 4 !== 0)@for($p=$ci%4;$p<4;$p++)<td></td>@endfor</tr>@endif
                </table>
            @endif

            {{-- Rollos asignados --}}
            @if(!empty($rolls['lines']))
                <div class="sec-title g-sec">Rollos Asignados</div>
                <table class="dt">
                    <thead><tr>
                        <th class="g-th">Consec.</th>
                        <th class="g-th">Tela</th>
                        <th class="g-th">Color</th>
                        <th class="tc g-th">Ancho</th>
                        <th class="tc g-th">Mts Prog.</th>
                        <th class="tc g-th">Saldo</th>
                        <th class="tr g-th">$/m</th>
                        <th class="g-th">Proveedor</th>
                    </tr></thead>
                    <tbody>
                        @foreach($rolls['lines'] as $line)
                            <tr>
                                <td class="nw">{{ $line['consecutive'] ?? '-' }}</td>
                                <td>{{ $line['fabricName'] ?? '-' }}</td>
                                <td>{{ $line['color'] ?? $line['colors'] ?? '-' }}{{ !empty($line['colorCode']) ? ' ('.$line['colorCode'].')' : '' }}</td>
                                <td class="tc">{{ $line['widthCm'] ?? '-' }}</td>
                                <td class="tc b">{{ isset($line['programmedMeters']) ? num($line['programmedMeters']) : '-' }}</td>
                                <td class="tc">{{ isset($line['balanceMeters']) ? num($line['balanceMeters']) : '-' }}</td>
                                <td class="tr nw">{{ isset($line['pricePerMeter']) ? money($line['pricePerMeter']) : '-' }}</td>
                                <td class="sm">{{ $line['supplier'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

        </td>
    </tr>
</table>

<div class="pg-footer g-foot">Ficha Técnica &nbsp;·&nbsp; Olamtex S.A.S. &nbsp;·&nbsp; Generada: {{ $printDate }} &nbsp;·&nbsp; Pág. 2 — CORTE Y NUMERACIÓN</div>
</div>


{{-- ╔══════════════════════════════════════════════════════════════╗ --}}
{{-- ║  PÁG. 3  ·  CONFECCIÓN                                      ║ --}}
{{-- ╚══════════════════════════════════════════════════════════════╝ --}}
<div class="page-break"></div>
<div class="page-frame p-page">

<table class="hdr p-hdr">
    <tr>
        <td class="hdr-logo">
            @if(file_exists(public_path('images/olamtex.png')))<img src="{{ public_path('images/olamtex.png') }}" alt="Olamtex" />@else<span class="logo-txt">OLAMTEX</span>@endif
        </td>
        <td class="hdr-center">
            <h1>Ficha Técnica · Confección</h1>
            <div class="sub">{{ $basic['opCode'] ?? '-' }} &nbsp;—&nbsp; {{ $reference ?? '-' }}</div>
        </td>
        <td class="hdr-meta">
            <strong>{{ $printDate }}</strong>
            OP: {{ $basic['opCode'] ?? '-' }}
        </td>
    </tr>
</table>
<div class="hdr-bar p-bar"></div>

<table class="layout">
    <tr>
        {{-- ─ IZQUIERDA 58%: Listado operacional + Insumos + Medidas ─ --}}
        <td class="col-data" style="width:58%;">

            {{-- Mini info --}}
            <table class="info-cards">
                <tr>
                    <td style="width:20%;"><span class="ic-lbl">Orden prod.</span><span class="ic-val b">{{ $basic['opCode'] ?? '-' }}</span></td>
                    <td style="width:25%;"><span class="ic-lbl">Referencia</span><span class="ic-val">{{ $basic['reference'] ?? '-' }}</span></td>
                    <td style="width:18%;"><span class="ic-lbl">Grupo / Marca</span><span class="ic-val">{{ $basic['group'] ?? '-' }} / {{ $basic['brand'] ?? '-' }}</span></td>
                    <td style="width:18%;"><span class="ic-lbl">Costo confección</span><span class="ic-val b">{{ !empty($processes['costConfeccion']) ? money($processes['costConfeccion']) : '-' }}</span></td>
                    <td style="width:19%;"><span class="ic-lbl">Total unidades</span><span class="ic-val b">{{ $basic['units'] ?? '-' }}</span></td>
                </tr>
            </table>

            {{-- LISTADO OPERACIONAL --}}
            @if(!empty($operationalList['hasList']) && !empty($operationalList['list']['items']))
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
                <div class="sec-title p-sec">Insumos de Confección</div>
                <table class="dt">
                    <thead><tr>
                        <th class="p-th">Insumo</th>
                        <th class="p-th">Descripción</th>
                        <th class="tc p-th">Moda/Línea</th>
                        <th class="tc p-th">Cant./Ud.</th>
                        <th class="tr p-th">Precio</th>
                    </tr></thead>
                    <tbody>
                        @foreach(array_merge($suppliesConf, $suppliesOther) as $s)
                            <tr>
                                <td class="b">{{ $s['name'] ?? $s['displayLabel'] ?? '-' }}</td>
                                <td class="sm">{{ $s['description'] ?? '-' }}</td>
                                <td class="tc sm">{{ $s['modaLinea'] ?? '-' }}</td>
                                <td class="tc b">{{ ($s['qtyPerUnit']??0)>0 ? num($s['qtyPerUnit'],3) : '-' }}</td>
                                <td class="tr nw">{{ ($s['unitCost']??0)>0 ? money($s['unitCost']) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- Tabla Medidas de Insumos por Talla --}}
            @if($hasSizeMeasurements && !empty($sizesDisplay))
                <div class="sec-title p-sec">Medidas de Insumos por Talla</div>
                <table class="dt">
                    <thead>
                        <tr>
                            <th class="p-th">Insumo</th>
                            <th class="p-th">Medida</th>
                            @foreach($sizesDisplay as $sz)<th class="tc p-th">{{ $sz }}</th>@endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sizeMeasurementRows as $s)
                            @php $ms = $s['sizeMeasurements']; @endphp
                            <tr>
                                <td rowspan="2" class="b" style="vertical-align:middle;">{{ $s['name'] ?? $s['displayLabel'] ?? '-' }}</td>
                                <td class="xs">Corte</td>
                                @foreach($sizesDisplay as $sz)<td class="tc">{{ isset($ms[$sz]['corte']) ? num($ms[$sz]['corte']) : '-' }}</td>@endforeach
                            </tr>
                            <tr>
                                <td class="xs">Termi.</td>
                                @foreach($sizesDisplay as $sz)<td class="tc">{{ isset($ms[$sz]['termi']) ? num($ms[$sz]['termi']) : '-' }}</td>@endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- Despiece --}}
            @if(!empty($additionalInfo['cutting']['pieces']))
                <div class="sec-title p-sec">Despiece</div>
                <table class="dt">
                    <thead><tr>
                        <th class="tc p-th">#</th>
                        <th class="p-th">Pieza</th>
                        <th class="p-th">Tela</th>
                        <th class="tc p-th">Cant.</th>
                        <th class="tc p-th">Lado</th>
                        <th class="tc p-th">Grano</th>
                    </tr></thead>
                    <tbody>
                        @foreach($additionalInfo['cutting']['pieces'] as $idx => $pc)
                            <tr>
                                <td class="tc">{{ $idx+1 }}</td>
                                <td class="b">{{ $pc['name'] ?? '-' }}</td>
                                <td>{{ $pc['type'] ?? '-' }}</td>
                                <td class="tc">{{ $pc['quantity'] ?? '-' }}</td>
                                <td class="tc">{{ $pc['side'] ?? '-' }}</td>
                                <td class="tc">{{ $pc['grain'] ?? '-' }}°</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

        </td>

        {{-- ─ DERECHA 42%: Dibujo + Colores + Hilos ─ --}}
        <td style="width:42%; vertical-align:top;">

            <div class="draw-wrap">
                <div class="draw-lbl p-dlbl">Dibujo Plano de Referencia</div>
                @if(!empty($drawingBase64))
                    <div class="draw-box p-draw">
                        <img src="{{ $drawingBase64 }}" alt="Dibujo plano" style="max-height:330px;" />
                    </div>
                @else
                    <div class="draw-empty p-draw" style="height:330px;">
                        <div class="draw-empty-inner" style="padding-top:150px;">Sin dibujo plano</div>
                    </div>
                @endif
            </div>

            @if(!empty($producedColors))
                <div class="clr-lbl p-clbl">Colores</div>
                <table class="clr-grid">
                    @php $ci=0; @endphp
                    @foreach($producedColors as $cN => $cC)
                        @if($ci%3===0)<tr>@endif
                        <td>
                            <div class="clr-name">{{ $cN }}</div>
                            @if($cC)<div class="clr-code p-code">{{ $cC }}</div>@endif
                        </td>
                        @php $ci++; @endphp
                        @if($ci%3===0)</tr>@endif
                    @endforeach
                    @if($ci%3!==0)@for($p=$ci%3;$p<3;$p++)<td></td>@endfor</tr>@endif
                </table>
            @endif

            @if(!empty($threads['lines']))
                <div class="sec-title p-sec">Hilos Asignados</div>
                <table class="dt">
                    <thead><tr>
                        <th class="p-th">Nombre</th>
                        <th class="tc p-th">Cal.</th>
                        <th class="p-th">Color</th>
                        <th class="tc p-th">Cód.</th>
                        <th class="tc p-th">P/pulg</th>
                    </tr></thead>
                    <tbody>
                        @foreach($threads['lines'] as $th)
                            <tr>
                                <td class="b sm">{{ $th['threadName'] ?? '-' }}</td>
                                <td class="tc">{{ $th['gauge'] ?? '-' }}</td>
                                <td class="sm">{{ $th['color'] ?? '-' }}</td>
                                <td class="tc">{{ $th['colorCode'] ?? '-' }}</td>
                                <td class="tc">{{ $th['stitchesPerInch'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

        </td>
    </tr>
</table>

<div class="pg-footer p-foot">Ficha Técnica &nbsp;·&nbsp; Olamtex S.A.S. &nbsp;·&nbsp; Generada: {{ $printDate }} &nbsp;·&nbsp; Pág. 3 — CONFECCIÓN</div>
</div>


{{-- ╔══════════════════════════════════════════════════════════════╗ --}}
{{-- ║  PÁG. 4  ·  TERMINACIÓN / ESTAMPADO / LOGÍSTICA             ║ --}}
{{-- ╚══════════════════════════════════════════════════════════════╝ --}}
<div class="page-break"></div>
<div class="page-frame a-page">

<table class="hdr a-hdr">
    <tr>
        <td class="hdr-logo">
            @if(file_exists(public_path('images/olamtex.png')))<img src="{{ public_path('images/olamtex.png') }}" alt="Olamtex" />@else<span class="logo-txt">OLAMTEX</span>@endif
        </td>
        <td class="hdr-center">
            <h1>Ficha Técnica · Terminación</h1>
            <div class="sub">{{ $basic['opCode'] ?? '-' }} &nbsp;—&nbsp; {{ $reference ?? '-' }}</div>
        </td>
        <td class="hdr-meta">
            <strong>{{ $printDate }}</strong>
            OP: {{ $basic['opCode'] ?? '-' }}
        </td>
    </tr>
</table>
<div class="hdr-bar a-bar"></div>

<table class="layout">
    <tr>
        {{-- ─ IZQUIERDA 58%: Insumos term + Medidas + Estampado + Lavandería ─ --}}
        <td class="col-data" style="width:58%;">

            {{-- Mini info --}}
            <table class="info-cards">
                <tr>
                    <td style="width:22%;"><span class="ic-lbl">Orden prod.</span><span class="ic-val b">{{ $basic['opCode'] ?? '-' }}</span></td>
                    <td style="width:25%;"><span class="ic-lbl">Referencia</span><span class="ic-val">{{ $basic['reference'] ?? '-' }}</span></td>
                    <td style="width:25%;"><span class="ic-lbl">Grupo / Marca</span><span class="ic-val">{{ $basic['group'] ?? '-' }} / {{ $basic['brand'] ?? '-' }}</span></td>
                    <td style="width:28%;"><span class="ic-lbl">Costo terminación</span><span class="ic-val b">{{ !empty($processes['costTerminacion']) ? money($processes['costTerminacion']) : '-' }}</span></td>
                </tr>
            </table>

            {{-- Colores × Tallas --}}
            @if(!empty($producedColors) && !empty($additionalInfo['cutting']['sizes']))
                <div class="sec-title a-sec">Distribución Colores × Tallas</div>
                <table class="dt">
                    <thead>
                        <tr>
                            <th class="a-th">Color</th>
                            @foreach($additionalInfo['cutting']['sizes'] as $sz)
                                <th class="tc a-th">{{ $sz['size'] ?? '-' }}</th>
                            @endforeach
                            <th class="tc a-th b">Total</th>
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

            {{-- Insumos Terminación --}}
            @if(!empty($suppliesTerm))
                <div class="sec-title a-sec">Insumos de Terminación</div>
                <table class="dt">
                    <thead><tr>
                        <th class="a-th">Insumo</th>
                        <th class="a-th">Descripción</th>
                        <th class="a-th">Proveedor</th>
                        <th class="tc a-th">Moda/Línea</th>
                        <th class="tc a-th">Cant./Ud.</th>
                        <th class="tr a-th">Precio</th>
                    </tr></thead>
                    <tbody>
                        @foreach($suppliesTerm as $idx => $s)
                            <tr>
                                <td class="b">{{ $s['name'] ?? $s['displayLabel'] ?? '-' }}</td>
                                <td class="sm">{{ $s['description'] ?? '-' }}</td>
                                <td class="sm">{{ $s['supplier'] ?? '-' }}</td>
                                <td class="tc sm">{{ $s['modaLinea'] ?? '-' }}</td>
                                <td class="tc b">{{ ($s['qtyPerUnit']??0)>0 ? num($s['qtyPerUnit'],3) : '-' }}</td>
                                <td class="tr nw">{{ ($s['unitCost']??0)>0 ? money($s['unitCost']) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- Tabla Medidas de Insumos por Talla --}}
            @if($hasSizeMeasurements && !empty($sizesDisplay))
                <div class="sec-title a-sec">Medidas de Insumos por Talla</div>
                <table class="dt">
                    <thead>
                        <tr>
                            <th class="a-th">Insumo</th>
                            <th class="a-th">Medida</th>
                            @foreach($sizesDisplay as $sz)<th class="tc a-th">{{ $sz }}</th>@endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sizeMeasurementRows as $s)
                            @php $ms = $s['sizeMeasurements']; @endphp
                            <tr>
                                <td rowspan="2" class="b" style="vertical-align:middle;">{{ $s['name'] ?? $s['displayLabel'] ?? '-' }}</td>
                                <td class="xs">M. Corte</td>
                                @foreach($sizesDisplay as $sz)<td class="tc">{{ isset($ms[$sz]['corte']) ? num($ms[$sz]['corte']) : '-' }}</td>@endforeach
                            </tr>
                            <tr>
                                <td class="xs">M. Termi.</td>
                                @foreach($sizesDisplay as $sz)<td class="tc">{{ isset($ms[$sz]['termi']) ? num($ms[$sz]['termi']) : '-' }}</td>@endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- Estampado --}}
            @if(!empty($additionalInfo['stamping']))
                @php $stamp = $additionalInfo['stamping']; @endphp
                <div class="sec-title a-sec">Estampado</div>
                @if(!empty($stamp['designNotes']))
                    <div class="note-box a-note"><strong>Notas de diseño:</strong> {{ $stamp['designNotes'] }}</div>
                @endif
                @if(!empty($stamp['attachments']))
                    <div class="sec-sub a-sub" style="margin-top:4px;">Imágenes de Estampado</div>
                    <div style="text-align:left; margin-bottom:5px;">
                        @foreach($stamp['attachments'] as $att)
                            @if(!empty($att['url']))<img src="{{ $att['url'] }}" alt="{{ $att['name'] ?? 'Estampado' }}" style="max-height:120px; max-width:160px; border:1px solid #dde5f0; margin:3px; object-fit:contain;" />@endif
                        @endforeach
                    </div>
                @endif
                @if(!empty($stamp['drawingAttachments']))
                    <div class="sec-sub a-sub" style="margin-top:4px;">Dibujos Técnicos de Estampado</div>
                    <div style="text-align:left; margin-bottom:5px;">
                        @foreach($stamp['drawingAttachments'] as $att)
                            @if(!empty($att['url']))<img src="{{ $att['url'] }}" alt="{{ $att['name'] ?? 'Dibujo' }}" style="max-height:120px; max-width:160px; border:1px solid #dde5f0; margin:3px; object-fit:contain;" />@endif
                        @endforeach
                    </div>
                @endif
            @endif

            {{-- Lavandería --}}
            @if(!empty($additionalInfo['laundry']))
                @php $laundry = $additionalInfo['laundry']; @endphp
                <div class="sec-title a-sec">Lavandería</div>
                @if(!empty($laundry['type']))
                    <div class="note-box a-note"><strong>Tipo:</strong> {{ $laundry['type'] }}</div>
                @endif
                @if(!empty($laundry['instructions']))
                    <div class="note-box a-note"><strong>Instrucciones:</strong> {{ $laundry['instructions'] }}</div>
                @endif
            @endif

        </td>

        {{-- ─ DERECHA 42%: Dibujo + Colores + Procesos + Hilos ─ --}}
        <td style="width:42%; vertical-align:top;">

            <div class="draw-wrap">
                <div class="draw-lbl a-dlbl">Dibujo Plano de Referencia</div>
                @if(!empty($drawingBase64))
                    <div class="draw-box a-draw">
                        <img src="{{ $drawingBase64 }}" alt="Dibujo plano" style="max-height:320px;" />
                    </div>
                @else
                    <div class="draw-empty a-draw" style="height:320px;">
                        <div class="draw-empty-inner" style="padding-top:145px;">Sin dibujo plano</div>
                    </div>
                @endif
            </div>

            @if(!empty($producedColors))
                <div class="clr-lbl a-clbl">Colores</div>
                <table class="clr-grid">
                    @php $ci=0; @endphp
                    @foreach($producedColors as $cN => $cC)
                        @if($ci%3===0)<tr>@endif
                        <td>
                            <div class="clr-name">{{ $cN }}</div>
                            @if($cC)<div class="clr-code a-code">{{ $cC }}</div>@endif
                        </td>
                        @php $ci++; @endphp
                        @if($ci%3===0)</tr>@endif
                    @endforeach
                    @if($ci%3!==0)@for($p=$ci%3;$p<3;$p++)<td></td>@endfor</tr>@endif
                </table>
            @endif

            {{-- Resumen Procesos --}}
            @if(!empty($processes['processes']))
                <div class="sec-title a-sec">Resumen de Procesos</div>
                <table class="dt">
                    <thead><tr>
                        <th class="a-th">Proceso</th>
                        <th class="a-th">Tipo</th>
                        <th class="tr a-th">Precio</th>
                    </tr></thead>
                    <tbody>
                        @foreach($processes['processes'] as $proc)
                            <tr>
                                <td class="b nw">P{{ $proc['order'] ?? '-' }}</td>
                                <td class="sm">{{ $proc['type'] ?? '-' }}</td>
                                <td class="tr nw">{{ isset($proc['manualPrice']) && $proc['manualPrice'] !== null ? money($proc['manualPrice']) : (isset($proc['fixedCost']) ? money($proc['fixedCost']) : '-') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if(!empty($threads['lines']))
                <div class="sec-title a-sec">Hilos Asignados</div>
                <table class="dt">
                    <thead><tr>
                        <th class="a-th">Nombre</th>
                        <th class="tc a-th">Cal.</th>
                        <th class="a-th">Marca</th>
                        <th class="tr a-th">Costo</th>
                    </tr></thead>
                    <tbody>
                        @php $ttc=0; @endphp
                        @foreach($threads['lines'] as $th)
                            @php $ttc += (float)($th['defaultThreadCost']??0); @endphp
                            <tr>
                                <td class="b sm">{{ $th['threadName'] ?? '-' }}</td>
                                <td class="tc">{{ $th['gauge'] ?? '-' }}</td>
                                <td class="sm">{{ $th['brand'] ?? '-' }}</td>
                                <td class="tr nw">{{ isset($th['defaultThreadCost']) ? money($th['defaultThreadCost']) : '-' }}</td>
                            </tr>
                        @endforeach
                        <tr class="row-total a-tot">
                            <td colspan="3" class="tr b">Total hilos:</td>
                            <td class="tr nw b">{{ money($ttc) }}</td>
                        </tr>
                    </tbody>
                </table>
            @endif

            @if(!empty($basic['ecommerce']))
                <div class="warn-box" style="margin-top:6px;"><strong>Nota E-Commerce:</strong> Verificar cantidades y empaque para despacho online.</div>
            @endif

            <div class="warn-box" style="margin-top:6px;"><strong>NOTA:</strong> Los códigos de colores deben pegarse en la hoja de bodega.</div>

        </td>
    </tr>
</table>

<div class="pg-footer a-foot">Ficha Técnica &nbsp;·&nbsp; Olamtex S.A.S. &nbsp;·&nbsp; Generada: {{ $printDate }} &nbsp;·&nbsp; Pág. 4 — TERMINACIÓN</div>

</div>

</body>
</html>
