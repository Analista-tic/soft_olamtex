<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductSheet;
use App\Models\Production\TechnicalSheet;
use App\Models\Production\TechnicalSheetStampingImage;
use App\Services\Production\TechnicalDataSheetAdditionalInfoStore;
use App\Services\Production\ProductSheetCuttingPieceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TechnicalDataSheetAdditionalInfoController extends Controller
{
    public function __construct(
        private TechnicalDataSheetAdditionalInfoStore $store,
        private ProductSheetCuttingPieceService $cuttingPieceService,
    ) {
    }

    public function show(int $opId): JsonResponse
    {
        $productionOrder = ProductionOrder::query()->findOrFail($opId);

        $data = $this->store->read($opId);
        $data['cutting'] = $this->emptyCuttingPayload();

        $sheet = TechnicalSheet::where('production_order_id', $opId)->first();

        if ($sheet) {
            $data['cutting']['params']['cutCostCop'] = $sheet->cut_cost !== null ? (float) $sheet->cut_cost : null;
        }

        // Piezas de corte: Leer desde ProductSheet (source of truth en BD)
        if ($productionOrder->productSheet) {
            $productSheet = $productionOrder->productSheet;
            if ($productSheet->cuttingPieces()->exists()) {
                $data['cutting']['pieces'] = $productSheet->cuttingPieces()
                    ->orderBy('sequence')
                    ->get()
                    ->map(fn ($p) => $this->mapPiece($p))
                    ->values()
                    ->all();
            }
        }

        // Tallas/unidades/curva: leer desde la tabla relacional (technical_sheet_sizes)
        if ($sheet) {
            $dbSizes = $sheet->sizes()->get()->map(fn ($s) => [
                'size'  => $s->size,
                'units' => (int) $s->units,
                'curve' => $s->curve !== null ? (string) $s->curve : null,
            ])->values()->all();

            if (!empty($dbSizes)) {
                $data['cutting']['sizes'] = $dbSizes;
            }

            // Imágenes de estampado/lavandería: BD toma precedencia.
            $stampingImgRecord = $sheet->stampingImages()
                ->where('section', TechnicalSheetStampingImage::SECTION_STAMPING)
                ->first();

            if ($stampingImgRecord) {
                $data['stamping']['attachments']        = $this->mapStampingImageToAttachment($stampingImgRecord, 'stamping');
                $data['stamping']['drawingAttachments'] = $this->mapStampingImageToAttachment($stampingImgRecord, 'drawing');
            }
        }

        return response()->json($data);
    }

    public function store(int $opId, Request $request): JsonResponse
    {
        $productionOrder = ProductionOrder::query()->findOrFail($opId);

        $validated = $request->validate([
            'cutting'                        => ['required', 'array'],
            'cutting.params'                 => ['required', 'array'],
            'cutting.params.cutCostCop'      => ['nullable', 'numeric', 'min:0'],
            'cutting.sizes'                  => ['nullable', 'array'],
            'cutting.sizes.*.size'           => ['required_with:cutting.sizes', 'string', 'max:50'],
            'cutting.sizes.*.units'          => ['required_with:cutting.sizes', 'numeric', 'min:0'],
            'cutting.sizes.*.curve'          => ['nullable', 'numeric', 'min:0'],
            'cutting.espigaLength'           => ['nullable', 'numeric', 'min:0'],
            'cutting.espigaCurve'            => ['nullable', 'string', 'max:100'],
            'cutting.liqLength'              => ['nullable', 'numeric', 'min:0'],
            'cutting.liqCurve'              => ['nullable', 'string', 'max:100'],
            'cutting.pieces'                 => ['nullable', 'array'],
            'cutting.pieces.*.id'            => ['required_with:cutting.pieces', 'string', 'max:100'],
            'cutting.pieces.*.name'          => ['required_with:cutting.pieces', 'string', 'max:150'],
            'cutting.pieces.*.type'          => ['nullable', 'in:PRINCIPAL,SECUNDARIO'],
            'cutting.pieces.*.fabricUid'     => ['nullable', 'string', 'max:100'],
            'cutting.pieces.*.side'          => ['required_with:cutting.pieces', 'in:IZQUIERDA,DERECHA,AMBOS,NO_APLICA'],
            'cutting.pieces.*.grain'         => ['required_with:cutting.pieces', 'in:90,180,SESGO'],
            'cutting.pieces.*.notes'         => ['nullable', 'string', 'max:1000'],
            'cutting.generalNotes'           => ['nullable', 'string', 'max:2000'],

            'stamping'                       => ['required', 'array'],
            'stamping.designNotes'           => ['nullable', 'string', 'max:2000'],
            'stamping.attachments'           => ['nullable', 'array'],
            'stamping.drawingAttachments'    => ['nullable', 'array'],

            'laundry'                        => ['required', 'array'],
            'laundry.instructions'           => ['nullable', 'string', 'max:2000'],
            'laundry.attachments'            => ['nullable', 'array'],
            'laundry.attachments.*.id'       => ['required_with:laundry.attachments', 'string', 'max:200'],
            'laundry.attachments.*.name'     => ['required_with:laundry.attachments', 'string', 'max:200'],
            'laundry.attachments.*.url'      => ['required_with:laundry.attachments', 'string', 'max:5000000'],
        ]);

        // 1. Persistir datos no-pieza en JSON (backward compat: params, espiga, notas, stamping, laundry)
        // Las piezas se guardan también en JSON como fallback para cuando no hay ProductSheet vinculado
        $this->store->write($opId, [
            'cutting'  => ['pieces' => $validated['cutting']['pieces'] ?? []],
            'stamping' => $validated['stamping'] ?? [],
            'laundry'  => [
                'instructions' => $validated['laundry']['instructions'] ?? null,
                'attachments'  => $validated['laundry']['attachments'] ?? [],
            ],
        ]);

        $this->syncCuttingMetaToDb($opId, $validated['cutting'] ?? []);

        // 2. Sincronizar piezas en BD usando ProductSheet (source of truth para el despiece de corte)
        if ($productionOrder->productSheet) {
            $pieces = is_array($validated['cutting']['pieces'] ?? null) ? $validated['cutting']['pieces'] : [];
            $this->syncCuttingPiecesToProductSheet($opId, $productionOrder->productSheet, $pieces);
        }

        // 2b. Sincronizar tallas/unidades/curva a nivel de ficha (tabla technical_sheet_sizes)
        $sizes = is_array($validated['cutting']['sizes'] ?? null) ? $validated['cutting']['sizes'] : [];
        $this->syncOpSizesToDb($opId, $sizes);

        // 3. Sincronizar imágenes de estampado en BD (source of truth para imágenes del diálogo)
        $this->syncStampingImagesToDB($opId, $validated['stamping'] ?? []);

        // 4. Responder con datos frescos: JSON + datos normalizados desde BD
        $responseData = $this->store->read($opId);
        $responseData['cutting'] = $this->emptyCuttingPayload();

        $sheet = TechnicalSheet::where('production_order_id', $opId)->first();
        if ($sheet) {
            $responseData['cutting']['params']['cutCostCop'] = $sheet->cut_cost !== null ? (float) $sheet->cut_cost : null;

            // Piezas desde ProductSheet
            if ($productionOrder->productSheet) {
                $productSheet = $productionOrder->productSheet;
                if ($productSheet->cuttingPieces()->exists()) {
                    $responseData['cutting']['pieces'] = $productSheet->cuttingPieces()
                        ->orderBy('sequence')
                        ->get()
                        ->map(fn ($p) => $this->mapPiece($p))
                        ->values()
                        ->all();
                }
            }

            // Agregar tallas/unidades/curva desde la tabla relacional
            $dbSizes = $sheet->sizes()->get()->map(fn ($s) => [
                'size'  => $s->size,
                'units' => (int) $s->units,
                'curve' => $s->curve !== null ? (string) $s->curve : null,
            ])->values()->all();

            if (!empty($dbSizes)) {
                $responseData['cutting']['sizes'] = $dbSizes;
            }

            $stampingImgRecord = $sheet->stampingImages()
                ->where('section', TechnicalSheetStampingImage::SECTION_STAMPING)
                ->first();

            if ($stampingImgRecord) {
                $responseData['stamping']['attachments']        = $this->mapStampingImageToAttachment($stampingImgRecord, 'stamping');
                $responseData['stamping']['drawingAttachments'] = $this->mapStampingImageToAttachment($stampingImgRecord, 'drawing');
            }
        }

        return response()->json($responseData);
    }

    /**
     * Sincroniza el desglose de tallas a nivel de ficha técnica (tabla technical_sheet_sizes).
     * Cada entrada debe tener: size, units, curve (curve es opcional).
     */
    private function syncOpSizesToDb(int $opId, array $sizes): void
    {
        $sheet = TechnicalSheet::firstOrCreate(
            ['production_order_id' => $opId],
            ['status' => TechnicalSheet::STATUS_DRAFT],
        );

        $incomingSizes = collect($sizes)
            ->filter(fn ($row) => isset($row['size']) && trim((string) ($row['size'] ?? '')) !== '')
            ->keyBy(fn ($row) => trim((string) $row['size']));

        // Eliminar tallas que ya no están en el payload
        $sheet->sizes()->whereNotIn('size', $incomingSizes->keys()->all())->delete();

        foreach ($incomingSizes as $size => $row) {
            $sheet->sizes()->updateOrCreate(
                ['size' => $size],
                [
                    'units' => max(0, (int) ($row['units'] ?? 0)),
                    'curve' => isset($row['curve']) && $row['curve'] !== '' && $row['curve'] !== null
                               ? (float) $row['curve']
                               : null,
                ]
            );
        }
    }

    private function syncCuttingMetaToDb(int $opId, array $cutting): void
    {
        $sheet = TechnicalSheet::firstOrCreate(
            ['production_order_id' => $opId],
            ['status' => TechnicalSheet::STATUS_DRAFT],
        );

        $cutCost = $cutting['params']['cutCostCop'] ?? null;
        $sheet->cut_cost = is_numeric($cutCost) ? max(0, (float) $cutCost) : null;
        $sheet->save();
    }

    private function emptyCuttingPayload(): array
    {
        return [
            'params' => [
                'cutCostCop' => null,
            ],
            'sizes' => [],
            'espigaLength' => null,
            'espigaCurve' => null,
            'liqLength' => null,
            'liqCurve' => null,
            'pieces' => [],
            'generalNotes' => null,
        ];
    }

    // ─── Helpers privados ──────────────────────────────────────────────────────

    /**
     * Sincroniza piezas de corte al ProductSheet usando ProductSheetCuttingPieceService.
     */
    private function syncCuttingPiecesToProductSheet(int $opId, ProductSheet $productSheet, array $pieces): void
    {
        $piecesPayload = [];
        
        foreach ($pieces as $index => $item) {
            $uid = $item['id'];
            $side = $item['side'] ?? 'AMBOS';
            
            $piecesPayload[] = [
                'piece_uid' => $uid,
                'sequence' => $index,
                'name' => $item['name'],
                'piece_type' => $item['type'] ?? 'PRINCIPAL',
                'fabric_type' => 'PRINCIPAL',
                'fabric_uid' => $item['fabricUid'] ?? null,
                'trace_location' => null,
                'side' => $side,
                'grain' => $item['grain'] ?? '90',
                'quantity' => $this->quantityFromSide($side),
                'notes' => $item['notes'] ?? null,
            ];
        }
        
        $this->cuttingPieceService->syncCuttingPieces($productSheet->id, $piecesPayload);
    }

    private function mapPiece($p): array
    {
        return [
            'id'            => (string) ($p->piece_uid ?? $p->id),
            'uid'           => (string) ($p->piece_uid ?? $p->id),
            'name'          => $p->name,
            'type'          => $p->piece_type,
            'fabricUid'     => $p->fabric_uid,
            'side'          => $p->side,
            'grain'         => $p->grain,
            'quantity'      => $p->quantity,
            'notes'         => $p->notes,
        ];
    }

    private function quantityFromSide(string $side): int
    {
        return match ($side) {
            'AMBOS'                   => 2,
            'IZQUIERDA', 'DERECHA' => 1,
            default                   => 0,
        };
    }

    /**
     * Sincroniza las imágenes de estampado con la BD (source of truth).
     * Usa updateOrCreate para mantener un registro único por (sheet, section).
     */
    private function syncStampingImagesToDB(int $opId, array $stampingPayload): void
    {
        $stampingAttachment = is_array($stampingPayload['attachments'] ?? null)
            ? ($stampingPayload['attachments'][0] ?? null)
            : null;

        $drawingAttachment = is_array($stampingPayload['drawingAttachments'] ?? null)
            ? ($stampingPayload['drawingAttachments'][0] ?? null)
            : null;

        // Solo sincronizar si hay datos de imagen (evitar crear registros vacíos).
        if (!$stampingAttachment && !$drawingAttachment) {
            return;
        }

        $sheet = TechnicalSheet::firstOrCreate(
            ['production_order_id' => $opId],
            ['status'              => TechnicalSheet::STATUS_DRAFT],
        );

        $sheet->stampingImages()->updateOrCreate(
            ['section' => TechnicalSheetStampingImage::SECTION_STAMPING],
            [
                'stamping_image_name' => $stampingAttachment ? ($stampingAttachment['name'] ?? null) : null,
                'stamping_image_data' => $stampingAttachment ? ($stampingAttachment['url'] ?? null) : null,
                'drawing_image_name'  => $drawingAttachment ? ($drawingAttachment['name'] ?? null) : null,
                'drawing_image_data'  => $drawingAttachment ? ($drawingAttachment['url'] ?? null) : null,
            ],
        );
    }

    /**
     * Convierte un registro TechnicalSheetStampingImage en el array de attachments
     * que espera el frontend: [{ id, name, url }] o [] si no hay imagen.
     *
     * @param  string  $type  'stamping' | 'drawing'
     */
    private function mapStampingImageToAttachment(TechnicalSheetStampingImage $record, string $type): array
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
}
