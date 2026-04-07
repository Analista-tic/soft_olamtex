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
        $supplies        = $this->suppliesStore->read($opId);
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
        $supplies        = $this->suppliesStore->read($opId);
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
            ->with(['operation:id,action,garment_part', 'operationSection:id,name,type'])
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
                    $sectionType  = $row->operationSection?->type;
                    $sectionName  = $row->operationSection?->name;

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

        $sheet = TechnicalSheet::where('production_order_id', $opId)->first();
        if (!$sheet) {
            return $data;
        }

        // Piezas de corte desde BD
        if ($sheet->cuttingPieces()->exists()) {
            $data['cutting']['pieces'] = $sheet->cuttingPieces()
                ->get()
                ->map(fn(TechnicalSheetCuttingPiece $p) => [
                    'id'        => (string) ($p->piece_uid ?? $p->id),
                    'name'      => $p->name,
                    'type'      => $p->piece_type,
                    'fabricUid' => $p->fabric_uid,
                    'side'      => $p->side,
                    'grain'     => $p->grain,
                    'quantity'  => $p->quantity,
                    'notes'     => $p->notes,
                ])
                ->values()
                ->all();
        }

        // Tallas/unidades/curva desde BD
        $dbSizes = $sheet->sizes()->get()->map(fn($s) => [
            'size'  => $s->size,
            'units' => (int) $s->units,
            'curve' => $s->curve !== null ? (string) $s->curve : null,
        ])->values()->all();
        if (!empty($dbSizes)) {
            $data['cutting']['sizes'] = $dbSizes;
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
        if (str_contains($path, '/storage/')) {
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

    private function buildPrintReference(array $basicInfo): string
    {
        $opCode = trim($basicInfo['opCode'] ?? '');
        $ref    = trim($basicInfo['reference'] ?? '');
        if ($opCode && $ref) {
            return "{$opCode} - {$ref}";
        }
        return $opCode ?: $ref ?: 'Sin referencia';
    }
}
