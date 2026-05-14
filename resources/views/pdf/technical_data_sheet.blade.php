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
    text-transform: uppercase;
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
.info-cards { width:100%; border-collapse:collapse; margin-bottom:6px; }
.info-cards td {
    padding:5px 8px; border:1px solid #dde5f0;
    background:#f6f9ff; vertical-align:top;
}
.ic-lbl {
    display:block; font-size:9pt; font-weight:700;
    text-transform:uppercase; letter-spacing:.4px;
    color:#6278a0; margin-bottom:2px;
}
.ic-val { font-size:9pt; font-weight:600; color:#1e2533; }

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
    font-size:9pt; font-weight:700; text-transform:uppercase;
    letter-spacing:.3px; padding:3px 5px;
    text-align:left; white-space:nowrap;
    border:.5px solid;
}
table.dt td {
    font-size:9pt; padding:2.8px 5px;
    border:.5px solid #dde5f0; vertical-align:top;
}
table.dt tbody tr:nth-child(even) { background:#edf8fd; }
table.dt .row-total { font-weight:700; }
table.dt .row-stage td {
    font-weight:700; font-size:9pt;
    text-transform:uppercase; letter-spacing:.3px; padding:3.5px 6px;
}

/* ════════════════════════════════════════════════════════════
   DRAWING  (right column)
   ════════════════════════════════════════════════════════════ */
.draw-wrap { margin-bottom:6px; }
.draw-lbl {
    font-size:9pt; font-weight:700; text-transform:uppercase;
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
    font-size:9pt; font-weight:700; text-transform:uppercase;
    letter-spacing:.4px; color:#fff; padding:3px 7px;
    margin-top:3px;
}
.clr-grid { width:100%; border-collapse:collapse; margin-bottom:5px; }
.clr-grid td {
    padding:4px 6px; text-align:center; vertical-align:middle;
    border:.5px solid #dde5f0; background:#f6f9ff;
}
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
.page-frame { padding: 2mm 2.4mm 1.6mm 2.4mm; }

/* ════════════════════════════════════════════════════════════
   PAGE FOOTER
   ════════════════════════════════════════════════════════════ */
.pg-footer {
    font-size:9pt; color:#fff; text-align:center;
    padding:3px 0; margin-top:4px;
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
            </table>
        </td>

        {{-- DERECHA 50%: Dibujo plano + Colores --}}
        <td style="width:50%; vertical-align:top;">
            <div class="draw-wrap">
                <div class="draw-lbl n-dlbl">Dibujo Plano de Referencia</div>
                @if(!empty($drawingBase64))
                    <div class="draw-box n-draw" style="height:380px; width:100%; padding:8px; box-sizing:border-box; overflow:hidden;">
                        <img src="{{ $drawingBase64 }}" alt="Dibujo plano" style="max-height:364px; max-width:100%; display:block; margin:0 auto; object-fit:contain;" />
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

</div>

{{-- ╔══════════════════════════════════════════════════════════════╗ --}}
{{-- ║  PÁG. 1  ·  PRECOSTEO                                       ║ --}}
{{-- ╚══════════════════════════════════════════════════════════════╝ --}}

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
                </tr>
            </table>

        </td>
    </tr>
</table>


</div>


{{-- ╔══════════════════════════════════════════════════════════════╗ --}}
{{-- ║  PÁG. 2  ·  CORTE Y NUMERACIÓN                              ║ --}}
{{-- ╚══════════════════════════════════════════════════════════════╝ --}}
<div class="page-break"></div>
<div class="page-frame">

<table class="layout">
    <tr>
        {{-- ─ IZQUIERDA 43%: info + MP + rollos + notas ─ --}}
        <td class="col-data" style="width:43%;">
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
    </tr>
</table>

</div>


{{-- ╔══════════════════════════════════════════════════════════════╗ --}}
{{-- ║  PÁG. 3  ·  CONFECCIÓN                                      ║ --}}
{{-- ╚══════════════════════════════════════════════════════════════╝ --}}
<div class="page-break"></div>
<div class="page-frame p-page">

<table class="layout">
    <tr>
        {{-- ─ COLUMNA ÚNICA: todo el contenido apilado verticalmente ─ --}}
        <td style="width:100%; vertical-align:top;">

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
                <div class="sec-title p-sec">Tabla de Medidas — Insumos de Confección</div>
                <table class="dt">
                    <thead>
                        <tr>
                            <th class="p-th">Insumo</th>
                            <th class="p-th">Medida</th>
                            @foreach($measSizes as $sz)<th class="tc p-th">{{ $sz }}</th>@endforeach
                            <th class="tc p-th">Prom. M.CORTE</th>
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
                                <td class="xs b">M.CORTE</td>
                                @foreach($measSizes as $sz)
                                    <td class="tc">{{ isset($ms[$sz]['corte']) ? num($ms[$sz]['corte']) : '-' }}</td>
                                @endforeach
                                <td class="tc b">{{ $promCorte !== null ? num($promCorte) : '-' }}</td>
                            </tr>
                            <tr>
                                <td class="xs b">M.TERMI</td>
                                @foreach($measSizes as $sz)
                                    <td class="tc">{{ isset($ms[$sz]['termi']) ? num($ms[$sz]['termi']) : '-' }}</td>
                                @endforeach
                                <td></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- Rollos (placeholder) --}}
            <div class="sec-title p-sec">Rollos</div>
            <table class="dt">
                <thead><tr>
                    <th class="p-th"></th>
                    <th class="p-th"></th>
                    <th class="p-th"></th>
                    <th class="p-th"></th>
                    <th class="p-th"></th>
                </tr></thead>
                <tbody>
                    <tr><td colspan="5" style="text-align:center; color:#aaa; font-size:9pt; padding:6px;">—</td></tr>
                </tbody>
            </table>

            {{-- Hilos Asignados --}}
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


</div>


{{-- ╔══════════════════════════════════════════════════════════════╗ --}}
{{-- ║  PÁG. 4  ·  TERMINACIÓN / ESTAMPADO / LOGÍSTICA             ║ --}}
{{-- ╚══════════════════════════════════════════════════════════════╝ --}}
<div class="page-break"></div>
<div class="page-frame a-page">

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
