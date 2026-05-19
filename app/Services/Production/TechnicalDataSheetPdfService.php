<?php

namespace App\Services\Production;

use App\Models\Production\ProductionOrder;
use App\Models\Production\TechnicalSheet;
use App\Models\Production\TechnicalSheetCuttingPiece;
use App\Models\Production\TechnicalSheetStampingImage;
use Barryvdh\DomPDF\Facade\Pdf as DomPDF;
use Illuminate\Support\Facades\Storage;

/**
 * Servicio para generar el PDF resumen completo de una Ficha Técnica.
 *
 * Recopila datos de todos los módulos (info básica, procesos, materia prima,
 * hilos, insumos, info adicional, costos) y genera un PDF consolidado.
 */
class TechnicalDataSheetPdfService
{
    public function __construct(
        private TechnicalSheetService $technicalSheetService,
        private TechnicalDataSheetRawMaterialsStore $rawMaterialsStore,
        private TechnicalDataSheetThreadsStore $threadsStore,
        private TechnicalDataSheetSuppliesStore $suppliesStore,
        private TechnicalDataSheetAdditionalInfoStore $additionalInfoStore,
        private TechnicalDataSheetRollsStore $rollsStore,
    ) {}

    /**
     * Genera el PDF de resumen completo y lo retorna como respuesta HTTP de descarga.
     */
    public function generate(int $opId, array $basicInfo): \Illuminate\Http\Response
    {
        $order = ProductionOrder::with('productSheet:id,operational_list_id')->findOrFail($opId);
        $productSheetId = $order->productSheet?->id;

        // ── Recopilar datos de cada sección ──
        $operationalList = $this->getOperationalList($opId);
        $processes       = $this->technicalSheetService->buildProcessesPayload($opId, $productSheetId);
        $rawMaterials    = $this->rawMaterialsStore->read($opId);
        $rolls           = $this->getRollsData($opId);
        $threads         = $this->getThreadsData($opId);
        $supplies        = $this->resolveSuppliesImages($this->suppliesStore->read($opId));
        $additionalInfo  = $this->getAdditionalInfoData($opId);
        $costs           = $this->technicalSheetService->costsSummary($opId);

        // ── Imagen del dibujo plano: convertir a base64 para embeber en PDF ──
        $drawingBase64 = $this->resolveDrawingBase64($basicInfo['flatDrawingUrl'] ?? null);

        // ── Referencia legible ──
        $reference = $this->buildPrintReference($basicInfo);

        $data = [
            'basic'           => $basicInfo,
            'reference'       => $reference,
            'printDate'       => now()->format('d/m/Y H:i'),
            'drawingBase64'   => $drawingBase64,
            'operationalList' => $operationalList,
            'processes'       => $processes,
            'rawMaterials'    => $rawMaterials,
            'rolls'           => $rolls,
            'threads'         => $threads,
            'supplies'        => $supplies,
            'additionalInfo'  => $additionalInfo,
            'costs'           => $costs,
        ];

        $pdf = DomPDF::loadView('pdf.technical_data_sheet', $data);
        $pdf->setPaper('letter', 'landscape');

        $filename = 'ficha_tecnica_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $basicInfo['opCode'] ?? 'op') . '.pdf';

        return $pdf->stream($filename);
    }

    /**
     * Genera el PDF horizontal (2 páginas, 3 columnas) y lo retorna como respuesta HTTP.
     */
    public function generateH(int $opId, array $basicInfo): \Illuminate\Http\Response
    {
        $order = ProductionOrder::with('productSheet:id,operational_list_id')->findOrFail($opId);
        $productSheetId = $order->productSheet?->id;

        $operationalList = $this->getOperationalList($opId);
        $processes       = $this->technicalSheetService->buildProcessesPayload($opId, $productSheetId);
        $rawMaterials    = $this->rawMaterialsStore->read($opId);
        $rolls           = $this->getRollsData($opId);
        $threads         = $this->getThreadsData($opId);
        $supplies        = $this->resolveSuppliesImages($this->suppliesStore->read($opId));
        $additionalInfo  = $this->getAdditionalInfoData($opId);
        $costs           = $this->technicalSheetService->costsSummary($opId);
        $drawingBase64   = $this->resolveDrawingBase64($basicInfo['flatDrawingUrl'] ?? null);
        $reference       = $this->buildPrintReference($basicInfo);

        $data = [
            'basic'           => $basicInfo,
            'reference'       => $reference,
            'printDate'       => now()->format('d/m/Y H:i'),
            'drawingBase64'   => $drawingBase64,
            'operationalList' => $operationalList,
            'processes'       => $processes,
            'rawMaterials'    => $rawMaterials,
            'rolls'           => $rolls,
            'threads'         => $threads,
            'supplies'        => $supplies,
            'additionalInfo'  => $additionalInfo,
            'costs'           => $costs,
        ];

        $pdf = DomPDF::loadView('pdf.technical_data_sheet_h', $data);
        $pdf->setPaper('letter', 'landscape');

        $filename = 'ficha_tecnica_h_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $basicInfo['opCode'] ?? 'op') . '.pdf';

        return $pdf->stream($filename);
    }

    /**
     * Obtiene el listado operacional formateado.
     */
    private function getOperationalList(int $opId): array
    {
        $order = ProductionOrder::with('productSheet:id,operational_list_id')->findOrFail($opId);
        $productSheet = $order->productSheet;

        if (!$productSheet || !$productSheet->operational_list_id) {
            return ['hasList' => false, 'list' => null];
        }

        $rows = \App\Models\Production\ProductOperationList::query()
            ->with(['operation:id,action,garment_part,section_id', 'operation.section:id,name,type'])
            ->where('product_sheet_id', $productSheet->id)
            ->orderBy('sequence')
            ->get();

        return [
            'hasList' => true,
            'list' => [
                'id'    => $productSheet->operational_list_id,
                'name'  => null,
                'items' => $rows->map(function ($row) {
                    $action       = trim((string) ($row->operation?->action ?? ''));
                    $garmentPart  = trim((string) ($row->operation?->garment_part ?? ''));
                    $operationName = trim("{$action} {$garmentPart}");
                    $sectionType  = $row->operation?->section?->type;
                    $sectionName  = $row->operation?->section?->name;

                    return [
                        'order'       => $row->sequence_order,
                        'process'     => $operationName !== '' ? $operationName : null,
                        'description' => $row->notes ?: null,
                        'minutes'     => $row->optimal_time !== null ? (float) $row->optimal_time : null,
                        'type'        => $sectionType ?: $sectionName,
                    ];
                })->values()->all(),
            ],
        ];
    }

    /**
     * Obtiene datos de rollos normalizados.
     */
    private function getRollsData(int $opId): array
    {
        return $this->rollsStore->read($opId);
    }

    /**
     * Obtiene datos de hilos normalizados.
     */
    private function getThreadsData(int $opId): array
    {
        return $this->threadsStore->read($opId);
    }

    /**
     * Obtiene la información adicional (corte, estampado, lavandería) incluyendo imágenes desde BD.
     */
    private function getAdditionalInfoData(int $opId): array
    {
        $data = $this->additionalInfoStore->read($opId);

        // Guardar curvas del snapshot ANTES de cualquier retorno anticipado
        $data['cutting']['sizeCurves'] = $data['cutting']['sizes'] ?? [];

        $order = ProductionOrder::find($opId);

        // Calcular colorCapas desde cut_fabric_consumption
        $cutConsumption = is_array($order?->cut_fabric_consumption) ? $order->cut_fabric_consumption : [];
        $consumptionLines = is_array($cutConsumption['lines'] ?? null) ? $cutConsumption['lines'] : [];
        $colorCapas = [];
        foreach ($consumptionLines as $line) {
            $colorName = trim((string) ($line['color'] ?? ''));
            if ($colorName === '') continue;
            $colorCode = trim((string) ($line['color_code'] ?? ''));
            if (!isset($colorCapas[$colorName])) {
                $colorCapas[$colorName] = ['color' => $colorName, 'colorCode' => $colorCode, 'layersFabric' => 0, 'layersLiq' => 0, 'layersHerringbone' => 0];
            }
            $colorCapas[$colorName]['layersFabric']     += (int) ($line['layers_fabric'] ?? 0);
            $colorCapas[$colorName]['layersLiq']        += (int) ($line['layers_liquidation'] ?? 0);
            $colorCapas[$colorName]['layersHerringbone'] += (int) ($line['layers_herringbone'] ?? 0);
        }
        $data['cutting']['colorCapas'] = array_values($colorCapas);

        $sheet = TechnicalSheet::where('production_order_id', $opId)->first();

        // Reconstruir cutFabricLines desde snapshots actuales para garantizar trazos correctos
        $data['cutFabricLines'] = $sheet
            ? $this->buildCutFabricLinesFromSnapshots($sheet, $consumptionLines)
            : $consumptionLines;

        if (!$sheet) {
            if (empty($data['cutting']['sizeCurves']) && !empty($cutConsumption['curves'])) {
                $data['cutting']['sizeCurves'] = $this->buildSizeCurvesFromConsumption($cutConsumption['curves']);
            }
            return $data;
        }

        // Piezas de corte desde ProductSheet (nueva source of truth)
        if ($order && $order->productSheet) {
            $productSheet = $order->productSheet;
            if ($productSheet->cuttingPieces()->exists()) {
                $data['cutting']['pieces'] = $productSheet->cuttingPieces()
                    ->orderBy('sequence')
                    ->get()
                    ->map(fn($p) => [
                        'id'        => (string) ($p->piece_uid ?? $p->id),
                        'name'      => $p->name,
                        'type'      => $p->piece_type,
                        'side'      => $p->side,
                        'grain'     => $p->grain,
                        'quantity'  => $p->quantity,
                        'notes'     => $p->notes,
                    ])
                    ->values()
                    ->all();
            }
        }

        // Guardar curvas (trazoPrincipal/liquidacion/espiga) del snapshot antes de sobrescribir
        $data['cutting']['sizeCurves'] = $data['cutting']['sizes'] ?? [];

        // Tallas/unidades/curva desde BD
        $dbSizeRows = $sheet->sizes()->get();
        $dbSizes = $dbSizeRows->map(fn($s) => [
            'size'  => $s->size,
            'units' => (int) ($s->total_unidades ?? 0),
            'curve' => null,
        ])->values()->all();
        if (!empty($dbSizes)) {
            $data['cutting']['sizes'] = $dbSizes;
        }

        // Curvas por talla desde BD (trazo_principal, liquidacion, espiga)
        $dbSizeCurves = $dbSizeRows->map(fn($s) => [
            'size'           => $s->size,
            'trazoPrincipal' => (int) $s->trazo_principal,
            'liquidacion'    => (int) $s->liquidacion,
            'espiga'         => (int) $s->espiga,
            'totalUnidades'  => (int) ($s->total_unidades ?? 0),
        ])->values()->all();
        if (!empty($dbSizeCurves)) {
            $data['cutting']['sizeCurves'] = $dbSizeCurves;
        }

        // Fallback: si sizeCurves sigue vacío, usar curves de cut_fabric_consumption
        if (empty($data['cutting']['sizeCurves']) && !empty($cutConsumption['curves'])) {
            $data['cutting']['sizeCurves'] = $this->buildSizeCurvesFromConsumption($cutConsumption['curves']);
        }

        // Imágenes de estampado desde BD
        $stampingImgRecord = $sheet->stampingImages()
            ->where('section', TechnicalSheetStampingImage::SECTION_STAMPING)
            ->first();
        if ($stampingImgRecord) {
            $data['stamping']['attachments']        = $this->mapStampingToArray($stampingImgRecord, 'stamping');
            $data['stamping']['drawingAttachments'] = $this->mapStampingToArray($stampingImgRecord, 'drawing');
        }

        return $data;
    }

    private function resolveSuppliesImages(array $supplies): array
    {
        return array_map(function (array $s): array {
            if (!empty($s['documentUrl'])) {
                $base64 = $this->resolveDrawingBase64($s['documentUrl']);
                $s['documentUrl'] = $base64 ?? null;
            }
            return $s;
        }, $supplies);
    }

    /**
     * Convierte la URL del dibujo plano a base64 data URI para embeber en el PDF.
     */
    private function resolveDrawingBase64(?string $url): ?string
    {
        if (!$url || trim($url) === '') {
            return null;
        }

        // Si ya es data URI, devolver directamente
        if (str_starts_with($url, 'data:')) {
            return $url;
        }

        // Intentar leer desde storage público
        $path = $url;
        if (str_contains($path, '/storage-file/')) {
            $path = preg_replace('#^.*/storage-file/#', '', $path);
        } elseif (str_contains($path, '/storage/')) {
            $path = preg_replace('#^.*/storage/#', '', $path);
        }
        $path = ltrim($path, '/');

        if (Storage::disk('public')->exists($path)) {
            $content  = Storage::disk('public')->get($path);
            $mime     = $this->guessMime($path);
            return "data:{$mime};base64," . base64_encode($content);
        }

        // Intentar variantes de ruta
        $candidates = [];
        if (str_starts_with($path, 'product-sheets/flat-sketches/')) {
            $candidates[] = str_replace('product-sheets/flat-sketches/', 'product_sheets/', $path);
        }
        if (str_starts_with($path, 'product_sheets/')) {
            $candidates[] = str_replace('product_sheets/', 'product-sheets/flat-sketches/', $path);
        }

        foreach ($candidates as $candidate) {
            if (Storage::disk('public')->exists($candidate)) {
                $content = Storage::disk('public')->get($candidate);
                $mime    = $this->guessMime($candidate);
                return "data:{$mime};base64," . base64_encode($content);
            }
        }

        return null;
    }

    private function guessMime(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'png'         => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif'         => 'image/gif',
            'svg'         => 'image/svg+xml',
            'webp'        => 'image/webp',
            default       => 'image/png',
        };
    }

    private function mapStampingToArray(TechnicalSheetStampingImage $record, string $type): array
    {
        if ($type === 'stamping') {
            if (!$record->stamping_image_data) return [];
            return [[
                'id'   => "stamping-{$record->id}",
                'name' => $record->stamping_image_name ?? 'stamping-image.jpg',
                'url'  => $record->stamping_image_data,
            ]];
        }

        if (!$record->drawing_image_data) return [];
        return [[
            'id'   => "drawing-{$record->id}",
            'name' => $record->drawing_image_name ?? 'drawing-image.jpg',
            'url'  => $record->drawing_image_data,
        ]];
    }

    private function buildSizeCurvesFromConsumption(array $curves): array
    {
        $principal   = is_array($curves['principal']   ?? null) ? $curves['principal']   : [];
        $liquidacion = is_array($curves['liquidacion'] ?? null) ? $curves['liquidacion'] : [];
        $espiga      = is_array($curves['espiga']      ?? null) ? $curves['espiga']      : [];
        $sizes = array_unique(array_merge(array_keys($principal), array_keys($liquidacion), array_keys($espiga)));
        $result = [];
        foreach ($sizes as $s) {
            $result[] = [
                'size'           => $s,
                'trazoPrincipal' => (int) ($principal[$s]   ?? 0),
                'liquidacion'    => (int) ($liquidacion[$s] ?? 0),
                'espiga'         => (int) ($espiga[$s]      ?? 0),
                'totalUnidades'  => 0,
            ];
        }
        return $result;
    }

    private function buildPrintReference(array $basicInfo): string
    {
        $opCode = trim($basicInfo['opCode'] ?? '');
        $ref    = trim($basicInfo['reference'] ?? '');
        if ($opCode && $ref) {
            return "{$opCode} - {$ref}";
        }
        return $opCode ?: $ref ?: 'Sin referencia';
    }

    private function buildCutFabricLinesFromSnapshots(TechnicalSheet $sheet, array $consumptionLines): array
    {
        // 1. Leer rollsSnapshot (datos actuales de rollos y metros asignados)
        $rollSnapshot = $sheet->rollsSnapshot()->first();
        $rollLines    = is_array($rollSnapshot?->lines) ? array_values($rollSnapshot->lines) : [];

        if (empty($rollLines)) {
            return $this->enrichTrazoUidFallback($consumptionLines, $sheet);
        }

        // 2. Leer rawMaterialsSnapshot (datos actuales de trazos)
        $rawSnapshot = $sheet->rawMaterialsSnapshot()->first();
        $rawRows     = is_array($rawSnapshot?->rows) ? array_values($rawSnapshot->rows) : [];

        // Mapa rawMaterialUid → {trace_uid, prom_trazo, largo_trazo, largo_liq}
        $rawUidMap = [];
        foreach ($rawRows as $row) {
            $uid = trim((string) ($row['uid'] ?? ''));
            if ($uid === '') continue;
            $rawUidMap[$uid] = [
                'trace_uid'   => trim((string) ($row['traceUid']   ?? '')) ?: null,
                'prom_trazo'  => is_numeric($row['promTrazo']  ?? null) ? (float) $row['promTrazo']  : null,
                'largo_trazo' => is_numeric($row['largoTrazo'] ?? null) ? (float) $row['largoTrazo'] : null,
                'largo_liq'   => is_numeric($row['largoLiq']   ?? null) ? (float) $row['largoLiq']   : null,
            ];
        }

        // Mapa technical_roll_id → línea de consumo (para campos de capas/consumo del BatchControl)
        $consumptionByTechId = [];
        foreach ($consumptionLines as $cl) {
            $techId = trim((string) ($cl['technical_roll_id'] ?? ''));
            if ($techId !== '') {
                $consumptionByTechId[$techId] = $cl;
            }
        }

        // 3. Construir líneas desde rollsSnapshot enriquecidas con datos de consumo
        $lines = [];
        foreach ($rollLines as $rl) {
            $programmedMeters = is_numeric($rl['programmedMeters'] ?? null) ? (float) $rl['programmedMeters'] : 0.0;
            if ($programmedMeters <= 0) {
                continue;
            }

            $rollLineId = trim((string) ($rl['id'] ?? ''));
            $rawUid     = trim((string) ($rl['rawMaterialUid'] ?? ''));
            $rawInfo    = $rawUidMap[$rawUid] ?? ['trace_uid' => null, 'prom_trazo' => null, 'largo_trazo' => null, 'largo_liq' => null];
            $consump    = $rollLineId !== '' ? ($consumptionByTechId[$rollLineId] ?? null) : null;
            $widthCm    = is_numeric($rl['widthCm'] ?? null) ? (float) $rl['widthCm'] : null;

            $lines[] = [
                'technical_roll_id'  => $rollLineId ?: null,
                'trazo_uid'          => $rawInfo['trace_uid'],
                'prom_trazo'         => $rawInfo['prom_trazo'],
                'largo_trazo'        => $rawInfo['largo_trazo'],
                'largo_liq'          => $rawInfo['largo_liq'],
                'fabric_reference'   => trim((string) ($rl['fabricName'] ?? '')),
                'color'              => $consump['color']      ?? (trim((string) ($rl['color']      ?? $rl['colors'] ?? '')) ?: null),
                'color_code'         => $consump['color_code'] ?? (trim((string) ($rl['colorCode']  ?? '')) ?: null),
                'fabric_type'        => strtoupper(trim((string) ($rl['type'] ?? ''))) ?: null,
                'location'           => $consump['location']   ?? (trim((string) ($rl['location']   ?? '')) ?: null),
                'roll_code'          => $consump['roll_code']  ?? (trim((string) ($rl['consecutive'] ?? '')) ?: null),
                'roll_width_m'       => $widthCm !== null ? round($widthCm / 100, 4) : null,
                'roll_total_meters'  => $consump['roll_total_meters']  ?? null,
                'meters_per_roll'    => $consump['meters_per_roll']    ?? $programmedMeters,
                'layers_fabric'      => $consump['layers_fabric']      ?? null,
                'layers_liquidation' => $consump['layers_liquidation'] ?? null,
                'layers_herringbone' => $consump['layers_herringbone'] ?? null,
                'total_consumption'  => $consump['total_consumption']  ?? null,
                'restante'           => $consump['restante']           ?? null,
                'retazo_1'           => $consump['retazo_1']           ?? null,
            ];
        }

        return !empty($lines)
            ? $lines
            : $this->enrichTrazoUidFallback($consumptionLines, $sheet);
    }

    private function enrichTrazoUidFallback(array $lines, TechnicalSheet $sheet): array
    {
        // Mapa: technical_roll_id → rawMaterialUid (desde rollsSnapshot)
        $techIdToRawUid = [];
        $rollSnapshot = $sheet->rollsSnapshot()->first();
        if ($rollSnapshot && is_array($rollSnapshot->lines)) {
            foreach ($rollSnapshot->lines as $rl) {
                $id     = trim((string) ($rl['id'] ?? ''));
                $rawUid = trim((string) ($rl['rawMaterialUid'] ?? ''));
                if ($id !== '' && $rawUid !== '') {
                    $techIdToRawUid[$id] = $rawUid;
                }
            }
        }

        // Mapa: rawMaterialUid → {traceUid, promTrazo} (desde rawMaterialsSnapshot)
        $rawUidToTrace = [];
        $rawSnapshot = $sheet->rawMaterialsSnapshot()->first();
        if ($rawSnapshot && is_array($rawSnapshot->rows)) {
            foreach ($rawSnapshot->rows as $row) {
                $uid      = trim((string) ($row['uid'] ?? ''));
                $traceUid = trim((string) ($row['traceUid'] ?? ''));
                if ($uid !== '' && $traceUid !== '') {
                    $rawUidToTrace[$uid] = [
                        'trace_uid'  => $traceUid,
                        'prom_trazo' => is_numeric($row['promTrazo'] ?? null) ? (float) $row['promTrazo'] : null,
                    ];
                }
            }
        }

        return array_map(function (array $line) use ($techIdToRawUid, $rawUidToTrace): array {
            if (!empty($line['trazo_uid'])) return $line;
            $techId = trim((string) ($line['technical_roll_id'] ?? ''));
            if ($techId === '') return $line;
            $rawUid = $techIdToRawUid[$techId] ?? null;
            if (!$rawUid || !isset($rawUidToTrace[$rawUid])) return $line;
            $trace = $rawUidToTrace[$rawUid];
            $line['trazo_uid'] = $trace['trace_uid'];
            if (empty($line['prom_trazo'])) $line['prom_trazo'] = $trace['prom_trazo'];
            return $line;
        }, $lines);
    }
}
