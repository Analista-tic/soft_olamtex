@php
    if (!function_exists('money_h')) {
        function money_h($value) {
            if ($value === null) return '-';
            return '$' . number_format((float) $value, 0, ',', '.');
        }
    }
    if (!function_exists('num_h')) {
        function num_h($value, $decimals = 2) {
            if ($value === null) return '-';
            return number_format((float) $value, $decimals, ',', '.');
        }
    }

    // ── Colores únicos desde rollos ──
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

    // ── Insumos por etapa ──
    $suppliesConf  = [];
    $suppliesTerm  = [];
    $suppliesOther = [];
    if (!empty($supplies)) {
        foreach ($supplies as $s) {
            $stage = strtoupper(trim($s['stage'] ?? ''));
            if (str_contains($stage, 'CONF'))                              $suppliesConf[]  = $s;
            elseif (str_contains($stage, 'TERM') || str_contains($stage, 'TERMI')) $suppliesTerm[]  = $s;
            else                                                            $suppliesOther[] = $s;
        }
    }
    if (empty($suppliesConf) && empty($suppliesTerm) && !empty($supplies)) {
        $suppliesOther = $supplies;
    }

    $sizesDisplay = is_array($basic['sizes'] ?? null) ? $basic['sizes'] : [];

    $hasSizeMeasurements = false;
    $sizeMeasurementRows = [];
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
<title>Ficha Técnica H · {{ $basic['opCode'] ?? 'OP' }}</title>
<style>
/* ════════════════════════════════════════════════════════════
   BASE  —  Letter Landscape  279.4 × 215.9 mm
   ════════════════════════════════════════════════════════════ */
@page { size: letter landscape; margin: 5mm 6mm 5mm 6mm; }
* { margin:0; padding:0; box-sizing:border-box; }
html, body {
    font-family: Arial, Helvetica, sans-serif;
    background: #fff;
    color: #1e2533;
    font-size: 8.5pt;
    line-height: 1.3;
}

/* ── HEADER ── */
.hdr { width:100%; border-collapse:collapse; margin-bottom:0; }
.hdr td { vertical-align:middle; padding:4px 9px; }
.hdr-logo { width:110px; text-align:center; background:#fff; border-right:3px solid rgba(255,255,255,.35); }
.hdr-logo img { max-height:28px; max-width:100px; }
.hdr-logo .logo-txt { font-size:11pt; font-weight:800; letter-spacing:2px; color:#1e3d70; }
.hdr-center { text-align:center; }
.hdr-center h1 { font-size:12pt; font-weight:700; color:#fff; margin:0; letter-spacing:.7px; text-transform:uppercase; }
.hdr-center .sub { font-size:8.5pt; color:rgba(255,255,255,.8); margin-top:2px; }
.hdr-meta { width:150px; text-align:right; font-size:8pt; color:rgba(255,255,255,.82); }
.hdr-meta strong { color:#fff; display:block; }
.hdr-bar { height:3px; margin-bottom:4px; }

/* ── PORTADA: banda info-rápida ── */
.quick-bar { width:100%; border-collapse:collapse; margin-bottom:5px; }
.quick-bar td {
    padding:4px 7px; border:1px solid #dde5f0;
    background:#f6f9ff; vertical-align:top;
}
.ql  { display:block; font-size:6.5pt; font-weight:700; text-transform:uppercase; letter-spacing:.3px; color:#6278a0; margin-bottom:1px; }
.qv  { font-size:9pt; font-weight:600; color:#1e2533; }

/* ── 3-COLUMN GRID ── */
.grid3 { width:100%; border-collapse:collapse; }
.grid3 > tbody > tr > td { vertical-align:top; padding:0; }
.c1 { padding-right:7px; }
.c2 { padding-right:7px; padding-left:0; }
.c3 { padding-left:0; }

/* ── SECCIONES ── */
.sec-title {
    font-size:8.5pt; font-weight:700; text-transform:uppercase;
    letter-spacing:.5px; padding:4px 8px; margin:5px 0 3px 0;
    border-left:4px solid;
}
.sec-sub {
    font-size:8.5pt; font-weight:700;
    padding:2px 0 2px 0; margin:4px 0 2px 0;
    border-bottom:2px solid;
}

/* ── TABLAS ── */
table.dt { width:100%; border-collapse:collapse; margin-bottom:4px; }
table.dt th {
    font-size:7.5pt; font-weight:700; text-transform:uppercase;
    letter-spacing:.3px; padding:2.5px 4px;
    text-align:left; white-space:nowrap; border:.5px solid;
}
table.dt td {
    font-size:8pt; padding:2.5px 4px;
    border:.5px solid #dde5f0; vertical-align:top;
}
table.dt tbody tr:nth-child(even) { background:#f4f8ff; }
table.dt .row-total { font-weight:700; }

/* ── DIBUJO ── */
.draw-lbl { font-size:7.5pt; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#fff; padding:3px 6px; }
.draw-box  { border:1.5px solid; padding:5px; background:#f8fbff; text-align:center; }
.draw-box img { max-width:100%; display:block; margin:0 auto; object-fit:contain; }
.draw-empty { border:2px dashed #b8cce4; background:#f4f7ff; text-align:center; }
.draw-empty-inner { font-size:8.5pt; color:#8a9bbf; }

/* ── COLORES ── */
.clr-lbl  { font-size:7.5pt; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#fff; padding:2.5px 6px; margin-top:3px; }
.clr-grid { width:100%; border-collapse:collapse; margin-bottom:4px; }
.clr-grid td { padding:3px 5px; text-align:center; vertical-align:middle; border:.5px solid #dde5f0; background:#f6f9ff; }
.clr-name { font-size:8pt; font-weight:700; color:#1e2533; }
.clr-code { font-size:7pt; font-weight:600; margin-top:1px; }

/* ── COST MINI ── */
.cost-mini { width:100%; border-collapse:collapse; margin-bottom:4px; }
.cost-mini td { padding:4px 6px; border:1px solid #dde5f0; background:#f6f9ff; vertical-align:top; }
.cost-pos { color:#1a6e3a !important; font-weight:700; }
.cost-neg { color:#b03030 !important; font-weight:700; }

.note-box { padding:3px 7px; margin-bottom:3px; font-size:8pt; border-left:4px solid; }
.warn-box  { padding:3px 7px; margin-bottom:3px; font-size:8pt; border-left:4px solid #d48a0a; background:#fffbec; color:#6e4a00; }

/* ── UTILIDADES ── */
.tr  { text-align:right; }
.tc  { text-align:center; }
.b   { font-weight:700; }
.nw  { white-space:nowrap; }
.sm  { font-size:7.5pt; }
.xs  { font-size:7pt; }
.page-break { page-break-before:always; }
.pf  { padding: 2mm 2mm 1.5mm 2mm; }

/* ── PIE DE PÁGINA ── */
.pg-footer { font-size:7.5pt; color:#fff; text-align:center; padding:3px 0; margin-top:4px; }

/* ════════════════════════════════════════════════════════════
   TOKENS DE COLOR  (misma paleta del original)
   P1/P2 → Navy   P3/P4 → incluidos con distinção inline
   ════════════════════════════════════════════════════════════ */
/* — NAVY — */
.n-hdr { background:#3a618d; } .n-bar { background:#84a7cc; }
.n-sec { background:#f1f5fa; border-left-color:#84a7cc; color:#34597f; }
.n-th  { background:#e2ecf8; color:#34597f; border-color:#c8d9ee; }
.n-stg td { background:#e2ecf8 !important; color:#34597f; }
.n-tot td { background:#e2ecf8 !important; font-weight:700; }
.n-foot { background:#3a618d; }
.n-note { background:#f3f7fc; border-left-color:#84a7cc; color:#2e4e72; }
.n-dlbl { background:#3a618d; } .n-clbl { background:#3a618d; }
.n-draw { border-color:#c8d9ee; } .n-code { color:#84a7cc; }

/* — GREEN — */
.g-hdr { background:#367252; } .g-bar { background:#6db28a; }
.g-sec { background:#f0f7f3; border-left-color:#6db28a; color:#2f694b; }
.g-th  { background:#e0f1e8; color:#2f694b; border-color:#c1dfce; }
.g-tot td { background:#e0f1e8 !important; font-weight:700; }
.g-foot { background:#367252; }
.g-note { background:#f4faf7; border-left-color:#6db28a; color:#285a41; }
.g-dlbl { background:#367252; } .g-clbl { background:#367252; }
.g-draw { border-color:#c1dfce; } .g-code { color:#6db28a; }
.g-stg td { background:#e0f1e8 !important; color:#2f694b; }

/* — PURPLE — */
.p-hdr { background:#654787; } .p-bar { background:#a688c7; }
.p-sec { background:#f6f1fa; border-left-color:#a688c7; color:#5b4179; }
.p-th  { background:#ece3f4; color:#5b4179; border-color:#d8c7ea; }
.p-tot td { background:#ece3f4 !important; font-weight:700; }
.p-foot { background:#654787; }
.p-note { background:#f8f4fb; border-left-color:#a688c7; color:#4f3869; }
.p-dlbl { background:#654787; } .p-clbl { background:#654787; }
.p-draw { border-color:#d8c7ea; } .p-code { color:#a688c7; }

/* — AMBER — */
.a-hdr { background:#94653a; } .a-bar { background:#d8a56b; }
.a-sec { background:#fef7ee; border-left-color:#d8a56b; color:#855a33; }
.a-th  { background:#f7e9d1; color:#855a33; border-color:#e8d0ac; }
.a-tot td { background:#f7e9d1 !important; font-weight:700; }
.a-foot { background:#94653a; }
.a-note { background:#fef9f1; border-left-color:#d8a56b; color:#6f4d2b; }
.a-dlbl { background:#94653a; } .a-clbl { background:#94653a; }
.a-draw { border-color:#e8d0ac; } .a-code { color:#d8a56b; }
</style>
</head>
<body>

{{-- ╔══════════════════════════════════════════════════════════════╗ --}}
{{-- ║  PÁGINA 1  ·  PRECOSTEO + CORTE (vista compacta horizontal)  ║ --}}
{{-- ╚══════════════════════════════════════════════════════════════╝ --}}
<div class="pf">

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
            <h1>Ficha Técnica · Precosteo y Corte</h1>
            <div class="sub">{{ $basic['opCode'] ?? '-' }} &nbsp;—&nbsp; {{ $reference ?? '-' }}</div>
        </td>
        <td class="hdr-meta">
            <strong>{{ $printDate }}</strong>
            {{ $basic['reference'] ?? '-' }}
        </td>
    </tr>
</table>
<div class="hdr-bar n-bar"></div>

{{-- ── BANDA DE INFO RÁPIDA (fila completa) ── --}}
<table class="quick-bar">
    <tr>
        <td style="width:11%;"><span class="ql">OP</span><span class="qv b">{{ $basic['opCode'] ?? '-' }}</span></td>
        <td style="width:16%;"><span class="ql">Referencia</span><span class="qv">{{ $basic['reference'] ?? '-' }}</span></td>
        <td style="width:8%;"><span class="ql">Fecha</span><span class="qv">{{ $basic['date'] ?? '-' }}</span></td>
        <td style="width:8%;"><span class="ql">Sección</span><span class="qv">{{ $basic['section'] ?? '-' }}</span></td>
        <td style="width:8%;"><span class="ql">Grupo</span><span class="qv">{{ $basic['group'] ?? '-' }}</span></td>
        <td style="width:8%;"><span class="ql">Marca</span><span class="qv">{{ $basic['brand'] ?? '-' }}</span></td>
        <td style="width:7%;"><span class="ql">Temporada</span><span class="qv">{{ $basic['season'] ?? '-' }}</span></td>
        <td style="width:7%;"><span class="ql">Unidades</span><span class="qv b">{{ $basic['units'] ?? '-' }}</span></td>
        <td style="width:9%;"><span class="ql">P. Venta</span><span class="qv b">{{ $basic['priceSale'] !== null ? money_h($basic['priceSale']) : '-' }}</span></td>
        <td style="width:9%;"><span class="ql">P. Facturación</span><span class="qv b">{{ $basic['priceBilling'] !== null ? money_h($basic['priceBilling']) : '-' }}</span></td>
        <td style="width:9%;"><span class="ql">Costo Total</span><span class="qv b">{{ money_h($costs['costs']['total'] ?? 0) }}</span></td>
    </tr>
</table>

{{-- ── CUERPO 3 COLUMNAS ── --}}
<table class="grid3">
    <tr>

        {{-- ─ COLUMNA 1 (31%)  Tallas + Materia Prima + Rollos ─ --}}
        <td class="c1" style="width:31%;">

            @if(!empty($additionalInfo['cutting']['sizes']))
                <div class="sec-title n-sec">Tallas · Curva · Unidades</div>
                <table class="dt">
                    <thead><tr>
                        <th class="n-th">Talla</th>
                        <th class="tc n-th">Curva</th>
                        <th class="tc n-th">Uds.</th>
                        <th class="n-th">Talla</th>
                        <th class="tc n-th">Curva</th>
                        <th class="tc n-th">Uds.</th>
                    </tr></thead>
                    <tbody>
                        @php
                            $szArr   = $additionalInfo['cutting']['sizes'];
                            $szPairs = array_chunk($szArr, 2);
                            $totalU  = array_sum(array_column($szArr, 'units'));
                        @endphp
                        @foreach($szPairs as $pair)
                            <tr>
                                @foreach($pair as $sz)
                                    <td class="b">{{ $sz['size'] ?? '-' }}</td>
                                    <td class="tc">{{ $sz['curve'] ?? '-' }}</td>
                                    <td class="tc b">{{ $sz['units'] ?? '-' }}</td>
                                @endforeach
                                @if(count($pair)<2)<td></td><td></td><td></td>@endif
                            </tr>
                        @endforeach
                        <tr class="row-total n-tot">
                            <td colspan="5" class="tr b">TOTAL:</td>
                            <td class="tc b">{{ $totalU }}</td>
                        </tr>
                    </tbody>
                </table>
            @elseif(!empty($sizesDisplay))
                <div class="sec-title n-sec">Tallas</div>
                <p style="font-size:8.5pt; padding:3px 0;">{{ implode(' · ', $sizesDisplay) }}</p>
            @endif

            @if(!empty($rawMaterials))
                <div class="sec-title n-sec">Materia Prima</div>
                <table class="dt">
                    <thead><tr>
                        <th class="n-th">M.P.</th>
                        <th class="n-th">Tela</th>
                        <th class="tc n-th">Ancho</th>
                        <th class="tc n-th">P.Trazo</th>
                        <th class="tc n-th">Consumo</th>
                        <th class="tr n-th">Precio/m</th>
                    </tr></thead>
                    <tbody>
                        @foreach($rawMaterials as $idx => $row)
                            <tr>
                                <td class="b nw sm">T{{ $idx+1 }}</td>
                                <td class="sm">{{ $row['tela'] ?? '-' }}</td>
                                <td class="tc">{{ isset($row['ancho']) ? num_h($row['ancho']) : '-' }}</td>
                                <td class="tc">{{ isset($row['promTrazo']) ? num_h($row['promTrazo'],3) : '-' }}</td>
                                <td class="tc b">{{ isset($row['consumo']) ? num_h($row['consumo'],3) : '-' }}</td>
                                <td class="tr nw">{{ ($row['lastUnitPriceCop']??0)>0 ? money_h($row['lastUnitPriceCop']) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if(!empty($rolls['lines']))
                <div class="sec-title n-sec">Rollos Asignados</div>
                <table class="dt">
                    <thead><tr>
                        <th class="n-th">Consec.</th>
                        <th class="n-th">Tela</th>
                        <th class="n-th">Color</th>
                        <th class="tc n-th">Mts.</th>
                        <th class="tr n-th">$/m</th>
                    </tr></thead>
                    <tbody>
                        @foreach($rolls['lines'] as $line)
                            <tr>
                                <td class="nw sm">{{ $line['consecutive'] ?? '-' }}</td>
                                <td class="sm">{{ $line['fabricName'] ?? '-' }}</td>
                                <td class="sm">{{ $line['color'] ?? $line['colors'] ?? '-' }}{{ !empty($line['colorCode']) ? ' ('.$line['colorCode'].')' : '' }}</td>
                                <td class="tc b">{{ isset($line['programmedMeters']) ? num_h($line['programmedMeters']) : '-' }}</td>
                                <td class="tr nw">{{ isset($line['pricePerMeter']) ? money_h($line['pricePerMeter']) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if(!empty($additionalInfo['cutting']))
                @php $c = $additionalInfo['cutting']; @endphp
                @if(!empty($c['espigaLength']) || !empty($c['liqLength']))
                    <div class="sec-title n-sec">Espiga / Liquidación</div>
                    <table class="quick-bar" style="margin-bottom:3px;">
                        <tr>
                            <td><span class="ql">Espiga largo</span><span class="qv">{{ $c['espigaLength'] ?? '-' }}</span></td>
                            <td><span class="ql">Espiga curva</span><span class="qv">{{ $c['espigaCurve'] ?? '-' }}</span></td>
                            <td><span class="ql">Liq. largo</span><span class="qv">{{ $c['liqLength'] ?? '-' }}</span></td>
                            <td><span class="ql">Liq. curva</span><span class="qv">{{ $c['liqCurve'] ?? '-' }}</span></td>
                        </tr>
                    </table>
                @endif
                @if(!empty($c['generalNotes']))
                    <div class="note-box n-note"><strong>Observaciones corte:</strong> {{ $c['generalNotes'] }}</div>
                @endif
            @endif

        </td>

        {{-- ─ COLUMNA 2 (33%)  Procesos + Costos Detalle + Despiece + Hilos ─ --}}
        <td class="c2" style="width:33%;">

            @if(!empty($processes['processes']))
                <div class="sec-title n-sec">Procesos de Producción</div>
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
                                <td class="sm">{{ $proc['type'] ?? '-' }}</td>
                                <td class="tr nw">{{ isset($proc['manualPrice']) && $proc['manualPrice'] !== null ? money_h($proc['manualPrice']) : (isset($proc['fixedCost']) ? money_h($proc['fixedCost']) : '-') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if(!empty($costs))
                <div class="sec-title n-sec">Resumen de Costos</div>
                <table class="cost-mini">
                    <tr>
                        <td><span class="ql">Materia prima</span><span class="qv">{{ money_h($costs['costs']['rawMaterial'] ?? 0) }}</span></td>
                        <td><span class="ql">MP / unidad</span><span class="qv">{{ money_h($costs['costs']['rawMaterialPerUnit'] ?? 0) }}</span></td>
                        <td><span class="ql">Insumos</span><span class="qv">{{ money_h($costs['costs']['supplies'] ?? 0) }}</span></td>
                    </tr>
                    <tr>
                        <td><span class="ql">Corte</span><span class="qv">{{ money_h($costs['costs']['cut'] ?? 0) }}</span></td>
                        <td><span class="ql">Confección</span><span class="qv">{{ money_h($costs['costs']['confection'] ?? 0) }}</span></td>
                        <td><span class="ql">Terminación</span><span class="qv">{{ money_h($costs['costs']['finishing'] ?? 0) }}</span></td>
                    </tr>
                    <tr>
                        <td><span class="ql">TOTAL COSTO</span><span class="qv b">{{ money_h($costs['costs']['total'] ?? 0) }}</span></td>
                        <td><span class="ql">Facturación</span><span class="qv b">{{ $costs['prices']['invoicing'] !== null ? money_h($costs['prices']['invoicing']) : '-' }}</span></td>
                        <td>
                            <span class="ql">Rentabilidad</span>
                            @if($costs['profitability']['olamtex']['value'] !== null)
                                <span class="qv {{ ($costs['profitability']['olamtex']['value'] ?? 0) >= 0 ? 'cost-pos' : 'cost-neg' }}">
                                    {{ money_h($costs['profitability']['olamtex']['value']) }}
                                    ({{ num_h($costs['profitability']['olamtex']['percent'] ?? 0, 2) }}%)
                                </span>
                            @else
                                <span class="qv">-</span>
                            @endif
                        </td>
                    </tr>
                </table>
            @endif

            @if(!empty($additionalInfo['cutting']['pieces']))
                <div class="sec-title n-sec">Despiece de Piezas</div>
                <table class="dt">
                    <thead><tr>
                        <th class="tc n-th">#</th>
                        <th class="n-th">Pieza</th>
                        <th class="n-th">Tipo</th>
                        <th class="tc n-th">Cant.</th>
                        <th class="tc n-th">Lado</th>
                        <th class="tc n-th">Grano</th>
                    </tr></thead>
                    <tbody>
                        @foreach($additionalInfo['cutting']['pieces'] as $idx => $pc)
                            <tr>
                                <td class="tc">{{ $idx+1 }}</td>
                                <td class="b sm">{{ $pc['name'] ?? '-' }}</td>
                                <td class="sm">{{ $pc['type'] ?? '-' }}</td>
                                <td class="tc">{{ $pc['quantity'] ?? '-' }}</td>
                                <td class="tc">{{ $pc['side'] ?? '-' }}</td>
                                <td class="tc">{{ $pc['grain'] ?? '-' }}°</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if(!empty($threads['lines']))
                <div class="sec-title n-sec">Hilos Asignados</div>
                <table class="dt">
                    <thead><tr>
                        <th class="n-th">Hilo</th>
                        <th class="tc n-th">Cal.</th>
                        <th class="n-th">Marca</th>
                        <th class="n-th">Color</th>
                        <th class="tc n-th">Cód.</th>
                        <th class="tc n-th">P/pulg</th>
                        <th class="tr n-th">Costo</th>
                    </tr></thead>
                    <tbody>
                        @php $ttc = 0; @endphp
                        @foreach($threads['lines'] as $th)
                            @php $ttc += (float)($th['defaultThreadCost']??0); @endphp
                            <tr>
                                <td class="b sm">{{ $th['threadName'] ?? '-' }}</td>
                                <td class="tc">{{ $th['gauge'] ?? '-' }}</td>
                                <td class="sm">{{ $th['brand'] ?? '-' }}</td>
                                <td class="sm">{{ $th['color'] ?? '-' }}</td>
                                <td class="tc sm">{{ $th['colorCode'] ?? '-' }}</td>
                                <td class="tc">{{ $th['stitchesPerInch'] ?? '-' }}</td>
                                <td class="tr nw">{{ isset($th['defaultThreadCost']) ? money_h($th['defaultThreadCost']) : '-' }}</td>
                            </tr>
                        @endforeach
                        <tr class="row-total n-tot">
                            <td colspan="6" class="tr b">Total costo hilos:</td>
                            <td class="tr nw b">{{ money_h($ttc) }}</td>
                        </tr>
                    </tbody>
                </table>
            @endif

        </td>

        {{-- ─ COLUMNA 3 (36%)  Dibujo grande + Colores ─ --}}
        <td class="c3" style="width:36%;">

            <div class="draw-lbl n-dlbl">Dibujo Plano de Referencia</div>
            @if(!empty($drawingBase64))
                <div class="draw-box n-draw" style="margin-bottom:5px;">
                    <img src="{{ $drawingBase64 }}" alt="Dibujo plano" style="max-height:390px;" />
                </div>
            @else
                <div class="draw-empty n-draw" style="height:390px; margin-bottom:5px;">
                    <div class="draw-empty-inner" style="padding-top:175px;">Sin dibujo plano</div>
                </div>
            @endif

            @if(!empty($producedColors))
                <div class="clr-lbl n-clbl">Colores Producidos</div>
                <table class="clr-grid">
                    @php $ci=0; @endphp
                    @foreach($producedColors as $cN => $cC)
                        @if($ci%4===0)<tr>@endif
                        <td>
                            <div class="clr-name">{{ $cN }}</div>
                            @if($cC)<div class="clr-code n-code">{{ $cC }}</div>@endif
                        </td>
                        @php $ci++; @endphp
                        @if($ci%4===0)</tr>@endif
                    @endforeach
                    @if($ci%4!==0)@for($p=$ci%4;$p<4;$p++)<td></td>@endfor</tr>@endif
                </table>
            @endif

            @if(!empty($basic['description']))
                <div class="note-box n-note" style="margin-top:4px;"><strong>Descripción:</strong> {{ $basic['description'] }}</div>
            @endif

        </td>
    </tr>
</table>

<div class="pg-footer n-foot">Ficha Técnica · Olamtex S.A.S. · Generada: {{ $printDate }} · Pág. 1 — PRECOSTEO Y CORTE</div>
</div>


{{-- ╔══════════════════════════════════════════════════════════════╗ --}}
{{-- ║  PÁGINA 2  ·  CONFECCIÓN + TERMINACIÓN (3 columnas)         ║ --}}
{{-- ╚══════════════════════════════════════════════════════════════╝ --}}
<div class="page-break"></div>
<div class="pf">

<table class="hdr p-hdr">
    <tr>
        <td class="hdr-logo">
            @if(file_exists(public_path('images/olamtex.png')))<img src="{{ public_path('images/olamtex.png') }}" alt="Olamtex" />@else<span class="logo-txt">OLAMTEX</span>@endif
        </td>
        <td class="hdr-center">
            <h1>Ficha Técnica · Confección y Terminación</h1>
            <div class="sub">{{ $basic['opCode'] ?? '-' }} &nbsp;—&nbsp; {{ $reference ?? '-' }}</div>
        </td>
        <td class="hdr-meta">
            <strong>{{ $printDate }}</strong>
            OP: {{ $basic['opCode'] ?? '-' }}
        </td>
    </tr>
</table>
<div class="hdr-bar p-bar"></div>

{{-- Banda inferior: datos clave de confección y terminación --}}
<table class="quick-bar">
    <tr>
        <td style="width:14%;"><span class="ql">OP</span><span class="qv b">{{ $basic['opCode'] ?? '-' }}</span></td>
        <td style="width:22%;"><span class="ql">Referencia</span><span class="qv">{{ $basic['reference'] ?? '-' }}</span></td>
        <td style="width:14%;"><span class="ql">Grupo / Marca</span><span class="qv">{{ $basic['group'] ?? '-' }} / {{ $basic['brand'] ?? '-' }}</span></td>
        <td style="width:12%;"><span class="ql">Total unidades</span><span class="qv b">{{ $basic['units'] ?? '-' }}</span></td>
        <td style="width:14%;"><span class="ql">Costo confección</span><span class="qv b">{{ !empty($processes['costConfeccion']) ? money_h($processes['costConfeccion']) : '-' }}</span></td>
        <td style="width:14%;"><span class="ql">Costo terminación</span><span class="qv b">{{ !empty($processes['costTerminacion']) ? money_h($processes['costTerminacion']) : '-' }}</span></td>
        <td style="width:10%;"><span class="ql">Costo total</span><span class="qv b">{{ money_h($costs['costs']['total'] ?? 0) }}</span></td>
    </tr>
</table>

<table class="grid3" style="margin-top:1px;">
    <tr>

        {{-- ─ COLUMNA 1 (36%)  Listado Operacional ─ --}}
        <td class="c1" style="width:36%;">

            @if(!empty($operationalList['hasList']) && !empty($operationalList['list']['items']))
                <div class="sec-title p-sec">Listado Operacional de Confección</div>
                <table class="dt">
                    <thead><tr>
                        <th class="tc p-th" style="width:24px;">#</th>
                        <th class="p-th">Operación / Proceso</th>
                        <th class="p-th">Descripción</th>
                        <th class="tc p-th" style="width:40px;">Min.</th>
                    </tr></thead>
                    <tbody>
                        @php $tMin = 0; @endphp
                        @foreach($operationalList['list']['items'] as $item)
                            @php $tMin += (float)($item['minutes'] ?? 0); @endphp
                            <tr>
                                <td class="tc b">{{ $item['order'] ?? '-' }}</td>
                                <td class="b sm">{{ $item['process'] ?? '-' }}</td>
                                <td class="sm">{{ $item['description'] ?? '' }}</td>
                                <td class="tc b">{{ $item['minutes'] !== null ? num_h($item['minutes']) : '-' }}</td>
                            </tr>
                        @endforeach
                        <tr class="row-total p-tot">
                            <td colspan="3" class="tr b">Total minutos ruta:</td>
                            <td class="tc b">{{ num_h($tMin) }}</td>
                        </tr>
                    </tbody>
                </table>
            @else
                <div class="sec-title p-sec">Listado Operacional</div>
                <p class="sm" style="padding:6px 0; color:#888;">Sin listado operacional registrado.</p>
            @endif

            {{-- Distribución Colores × Tallas --}}
            @if(!empty($producedColors) && !empty($additionalInfo['cutting']['sizes']))
                <div class="sec-title p-sec">Distribución Colores × Tallas</div>
                <table class="dt">
                    <thead>
                        <tr>
                            <th class="p-th">Color</th>
                            @foreach($additionalInfo['cutting']['sizes'] as $sz)
                                <th class="tc p-th">{{ $sz['size'] ?? '-' }}</th>
                            @endforeach
                            <th class="tc p-th b">Tot.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($producedColors as $cN => $cC)
                            <tr>
                                <td class="b sm">{{ $cN }}</td>
                                @foreach($additionalInfo['cutting']['sizes'] as $sz)
                                    <td class="tc">{{ $sz['units'] ?? '-' }}</td>
                                @endforeach
                                <td class="tc b">-</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

        </td>

        {{-- ─ COLUMNA 2 (32%)  Insumos Confección + Insumos Terminación + Medidas ─ --}}
        <td class="c2" style="width:32%;">

            @if(!empty($suppliesConf) || !empty($suppliesOther))
                <div class="sec-title p-sec">Insumos de Confección</div>
                <table class="dt">
                    <thead><tr>
                        <th class="p-th">Insumo</th>
                        <th class="p-th">Descripción</th>
                        <th class="tc p-th">Cant./Ud.</th>
                        <th class="tr p-th">Precio</th>
                    </tr></thead>
                    <tbody>
                        @foreach(array_merge($suppliesConf, $suppliesOther) as $s)
                            <tr>
                                <td class="b sm">{{ $s['name'] ?? $s['displayLabel'] ?? '-' }}</td>
                                <td class="sm">{{ $s['description'] ?? '-' }}</td>
                                <td class="tc b">{{ ($s['qtyPerUnit']??0)>0 ? num_h($s['qtyPerUnit'],3) : '-' }}</td>
                                <td class="tr nw">{{ ($s['unitCost']??0)>0 ? money_h($s['unitCost']) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if(!empty($suppliesTerm))
                <div class="sec-title a-sec">Insumos de Terminación</div>
                <table class="dt">
                    <thead><tr>
                        <th class="a-th">Insumo</th>
                        <th class="a-th">Descripción</th>
                        <th class="tc a-th">Cant./Ud.</th>
                        <th class="tr a-th">Precio</th>
                    </tr></thead>
                    <tbody>
                        @foreach($suppliesTerm as $s)
                            <tr>
                                <td class="b sm">{{ $s['name'] ?? $s['displayLabel'] ?? '-' }}</td>
                                <td class="sm">{{ $s['description'] ?? '-' }}</td>
                                <td class="tc b">{{ ($s['qtyPerUnit']??0)>0 ? num_h($s['qtyPerUnit'],3) : '-' }}</td>
                                <td class="tr nw">{{ ($s['unitCost']??0)>0 ? money_h($s['unitCost']) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if($hasSizeMeasurements && !empty($sizesDisplay))
                <div class="sec-title p-sec">Medidas de Insumos por Talla</div>
                <table class="dt">
                    <thead>
                        <tr>
                            <th class="p-th">Insumo</th>
                            <th class="p-th">Med.</th>
                            @foreach($sizesDisplay as $sz)<th class="tc p-th">{{ $sz }}</th>@endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sizeMeasurementRows as $s)
                            @php $ms = $s['sizeMeasurements']; @endphp
                            <tr>
                                <td rowspan="2" class="b" style="vertical-align:middle; font-size:7.5pt;">{{ $s['name'] ?? $s['displayLabel'] ?? '-' }}</td>
                                <td class="xs">Corte</td>
                                @foreach($sizesDisplay as $sz)<td class="tc xs">{{ isset($ms[$sz]['corte']) ? num_h($ms[$sz]['corte']) : '-' }}</td>@endforeach
                            </tr>
                            <tr>
                                <td class="xs">Termi.</td>
                                @foreach($sizesDisplay as $sz)<td class="tc xs">{{ isset($ms[$sz]['termi']) ? num_h($ms[$sz]['termi']) : '-' }}</td>@endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- Estampado notas --}}
            @if(!empty($additionalInfo['stamping']['designNotes']))
                <div class="sec-title a-sec">Estampado</div>
                <div class="note-box a-note"><strong>Notas:</strong> {{ $additionalInfo['stamping']['designNotes'] }}</div>
            @endif

            {{-- Lavandería --}}
            @if(!empty($additionalInfo['laundry']))
                @php $laundry = $additionalInfo['laundry']; @endphp
                <div class="sec-title a-sec">Lavandería</div>
                @if(!empty($laundry['type']))<div class="note-box a-note"><strong>Tipo:</strong> {{ $laundry['type'] }}</div>@endif
                @if(!empty($laundry['instructions']))<div class="note-box a-note"><strong>Instrucciones:</strong> {{ $laundry['instructions'] }}</div>@endif
            @endif

        </td>

        {{-- ─ COLUMNA 3 (32%)  Dibujo + Colores + Estampado Imágenes + Notas ─ --}}
        <td class="c3" style="width:32%;">

            <div class="draw-lbl p-dlbl">Dibujo Plano de Referencia</div>
            @if(!empty($drawingBase64))
                <div class="draw-box p-draw" style="margin-bottom:5px;">
                    <img src="{{ $drawingBase64 }}" alt="Dibujo plano" style="max-height:310px;" />
                </div>
            @else
                <div class="draw-empty p-draw" style="height:310px; margin-bottom:5px;">
                    <div class="draw-empty-inner" style="padding-top:135px;">Sin dibujo plano</div>
                </div>
            @endif

            @if(!empty($producedColors))
                <div class="clr-lbl p-clbl">Colores Producidos</div>
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

            {{-- Imágenes estampado si las hay --}}
            @if(!empty($additionalInfo['stamping']['attachments']))
                <div class="sec-sub p-sub" style="color:#5b4179; border-bottom-color:#a688c7; margin-top:4px;">Imágenes de Estampado</div>
                <div style="text-align:left; margin-bottom:4px;">
                    @foreach($additionalInfo['stamping']['attachments'] as $att)
                        @if(!empty($att['url']))<img src="{{ $att['url'] }}" alt="{{ $att['name'] ?? 'Estampado' }}" style="max-height:100px; max-width:130px; border:1px solid #dde5f0; margin:2px; object-fit:contain;" />@endif
                    @endforeach
                </div>
            @endif

            @if(!empty($basic['ecommerce']))
                <div class="warn-box" style="margin-top:5px;"><strong>E-Commerce:</strong> Verificar cantidades y empaque para despacho online.</div>
            @endif

            <div class="warn-box" style="margin-top:5px;"><strong>NOTA:</strong> Los códigos de colores deben pegarse en la hoja de bodega.</div>

        </td>
    </tr>
</table>


</div>

</body>
</html>
