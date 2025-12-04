<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Article;
use App\Models\Inventory\InventoryAdjustment;
use App\Models\Inventory\InventoryAdjustmentLine;
use App\Models\Common\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryAdjustmentController extends Controller
{
    /**
     * Devuelve los ajustes paginados para la tabla (API).
     */
    public function getData(Request $request)
    {
        $query = InventoryAdjustment::query();

        if ($request->has('search') && trim($request->search) !== '') {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('document_number', 'like', "%{$search}%")
                  ->orWhere('observation', 'like', "%{$search}%");
            });
        }

        if ($request->has('type') && in_array($request->type, ['entrada', 'salida'])) {
            $query->where('type', $request->type);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('date', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        $perPage = $request->input('per_page', 10);
        $adjustments = $query
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return response()->json($adjustments);
    }

    /**
     * (Opcional) Listado simple o redireccion.
     */
    public function index()
    {
        return response()->json([
            'message' => 'Use /ajustes-inventario/data para obtener los ajustes paginados.'
        ]);
    }

    /**
     * Devuelve un ajuste con sus lineas.
     */
    public function show($id)
    {
        $adjustment = InventoryAdjustment::with(['lines.article', 'lines.warehouse'])->findOrFail($id);
        return response()->json($adjustment);
    }

    /**
     * Guarda un nuevo ajuste de inventario.
     */
    public function store(Request $request)
    {
        $movementReasons = $this->movementReasons();
        $this->decodeLinesJson($request);

        try {
            $data = $this->validateAdjustment($request, $movementReasons);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        }

        $data = $this->preparePayload($data, $movementReasons);

        try {
            $articles = $this->fetchArticlesByLines($data['lines']);
            $adjustment = $this->persistAdjustment($data, $articles, null, $movementReasons);

            return response()->json([
                'message' => 'Ajuste de inventario creado correctamente',
                'data'    => $adjustment,
            ], 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Ocurrio un error al guardar el ajuste de inventario.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualiza un ajuste existente.
     */
    public function update(Request $request, $id)
    {
        $adjustment = InventoryAdjustment::findOrFail($id);
        $movementReasons = $this->movementReasons();
        $this->decodeLinesJson($request);

        try {
            $data = $this->validateAdjustment($request, $movementReasons, $adjustment);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        }

        $data = $this->preparePayload($data, $movementReasons);

        try {
            $articles = $this->fetchArticlesByLines($data['lines']);
            $updated = $this->persistAdjustment($data, $articles, $adjustment, $movementReasons);

            return response()->json([
                'message' => 'Ajuste de inventario actualizado correctamente',
                'data'    => $updated,
            ]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Ocurrio un error al actualizar el ajuste de inventario.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Elimina un ajuste y sus lineas.
     */
    public function destroy($id)
    {
        $adjustment = InventoryAdjustment::findOrFail($id);

        DB::transaction(function () use ($adjustment) {
            foreach ($adjustment->lines as $line) {
                $article = Article::find($line->article_id);
                if ($article) {
                    $warehouseId = $this->resolveWarehouseId($article, $line->warehouse_id);
                    $reverseType = $adjustment->type === 'entrada' ? 'salida' : 'entrada';
                    $this->applyStockMovement($article, $warehouseId, (int) $line->quantity, $reverseType);
                }
            }

            $adjustment->lines()->delete();
            $adjustment->delete();
        });

        return response()->noContent();
    }

    /**
     * Devuelve una bodega valida para el articulo, o lanza error si falta.
     */
    private function resolveWarehouseId(Article $article, ?int $warehouseId): int
    {
        if ($warehouseId) {
            return $warehouseId;
        }

        $warehouse = $article->warehouses()->first();
        if ($warehouse) {
            return $warehouse->id;
        }

        $fallback = Warehouse::first();
        if ($fallback) {
            $article->warehouses()->attach($fallback->id, [
                'quantity' => 0,
                'stock_min' => 0,
                'stock_max' => 0,
            ]);
            return $fallback->id;
        }

        throw ValidationException::withMessages([
            'lines' => ["No hay bodegas configuradas. Crea una bodega o envia warehouse_id."],
        ]);
    }

    /**
     * Aplica el movimiento de inventario en la tabla pivot warehouse_article.
     */
    private function applyStockMovement(Article $article, int $warehouseId, int $quantity, string $type): void
    {
        $quantity = abs($quantity);
        $sign = $type === 'entrada' ? 1 : -1;

        $row = DB::table('warehouse_article')
            ->where('article_id', $article->id)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        $currentQty = $row ? (int) $row->quantity : 0;
        $newQty = $currentQty + ($sign * $quantity);

        if ($newQty < 0 && !$article->negative_movement) {
            $article->negative_movement = 1;
            $article->save();
        }

        $quantityToStore = $article->negative_movement ? $newQty : max($newQty, 0);

        $payload = [
            'quantity'   => $quantityToStore,
            'stock_min'  => $row ? $row->stock_min : 0,
            'stock_max'  => $row ? $row->stock_max : 0,
            'updated_at' => now(),
        ];

        if ($row) {
            DB::table('warehouse_article')
                ->where('id', $row->id)
                ->update($payload);
        } else {
            $payload['article_id'] = $article->id;
            $payload['warehouse_id'] = $warehouseId;
            $payload['created_at'] = now();
            DB::table('warehouse_article')->insert($payload);
        }
    }

    /**
     * Genera un numero consecutivo unico para el ajuste de inventario.
     */
    private function generateDocumentNumber(): string
    {
        $prefix = 'OL';
        $sequenceLength = 3;

        $lastNumber = InventoryAdjustment::whereNotNull('document_number')
            ->where('document_number', 'like', "{$prefix}-%")
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('document_number');

        $next = 1;
        if ($lastNumber && preg_match("/^{$prefix}--(\\d{{$sequenceLength}})$/", $lastNumber, $matches)) {
            $next = (int) $matches[1] + 1;
        }

        $sequence = str_pad((string) $next, $sequenceLength, '0', STR_PAD_LEFT);

        return "{$prefix}-{$sequence}";
    }

    /**
     * Devuelve el siguiente numero consecutivo sugerido (para mostrar en el formulario).
     */
    public function nextNumber()
    {
        $next = DB::transaction(fn() => $this->generateDocumentNumber());

        return response()->json(['document_number' => $next]);
    }

    /**
     * Mapeo de motivos de ajuste a tipo de movimiento (entrada/salida).
     */
    private function movementReasons(): array
    {
        return [
            'entrada' => 'entrada',
            'salida' => 'salida',
        ];
    }

    private function decodeLinesJson(Request $request): void
    {
        if ($request->has('lines') && is_string($request->input('lines'))) {
            $decoded = json_decode($request->input('lines'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $request->merge(['lines' => $decoded]);
            }
        }
    }

    private function validateAdjustment(Request $request, array $movementReasons, ?InventoryAdjustment $adjustment = null): array
    {
        $uniqueRule = Rule::unique('inventory_adjustments', 'document_number');
        if ($adjustment) {
            $uniqueRule->ignore($adjustment->id);
        }

        $rules = [
            'document_number'      => [
                'nullable',
                'string',
                'max:255',
                $uniqueRule,
            ],
            'date'                 => 'required|date',
            'type'                 => 'required|in:entrada,salida',
            'movement_reason'      => ['required', Rule::in(array_keys($movementReasons))],
            'observation'          => 'nullable|string',
            'production_order'     => 'nullable|string|max:255',
            'subtotal'             => 'nullable|numeric',
            'iva'                  => 'nullable|numeric',
            'total'                => 'nullable|numeric',
            'lines'                => 'required|array|min:1',
            'lines.*.article_id'   => 'required|integer|exists:articles,id',
            'lines.*.warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'lines.*.price'        => 'required|numeric|min:0',
            'lines.*.tax'          => 'nullable|numeric|min:0',
            'lines.*.quantity'     => 'required|integer|min:1',
            'lines.*.movement_reason' => ['required', Rule::in(array_keys($movementReasons))],
            'lines.*.description'  => 'required|string|min:1',
        ];

        return $request->validate($rules);
    }

    private function validationErrorResponse(ValidationException $exception)
    {
        return response()->json([
            'message' => 'Errores de validacion',
            'errors'  => $exception->errors(),
        ], 422);
    }

    private function preparePayload(array $data, array $movementReasons): array
    {
        $data['type'] = $movementReasons[$data['movement_reason']] ?? $data['type'];
        $data['document_number'] = $data['document_number'] ?: null;

        [$subtotal, $iva] = $this->calculateTotals($data['lines']);
        $data['subtotal'] = $subtotal;
        $data['iva']      = $iva;
        $data['total']    = $subtotal + $iva;

        return $data;
    }

    private function calculateTotals(array $lines): array
    {
        $subtotal = 0;
        $ivaTotal = 0;

        foreach ($lines as $line) {
            $price = (float) ($line['price'] ?? 0);
            $qty   = (int) ($line['quantity'] ?? 0);
            $tax   = (float) ($line['tax'] ?? 0);

            $base = $price * $qty;
            $taxAmount = $base * $tax / 100;

            $subtotal += $base;
            $ivaTotal += $taxAmount;
        }

        return [$subtotal, $ivaTotal];
    }

    private function fetchArticlesByLines(array $lines): Collection
    {
        return Article::with('warehouses')
            ->whereIn('id', collect($lines)->pluck('article_id'))
            ->get()
            ->keyBy('id');
    }

    private function persistAdjustment(array $data, Collection $articles, ?InventoryAdjustment $adjustment = null, array $movementReasons = []): InventoryAdjustment
    {
        return DB::transaction(function () use ($data, $articles, $adjustment, $movementReasons) {
            $documentNumber = $data['document_number'] ?: ($adjustment->document_number ?? $this->generateDocumentNumber());

            if (!$adjustment) {
                $adjustment = InventoryAdjustment::create([
                    'document_number'  => $documentNumber,
                    'date'             => $data['date'],
                    'type'             => $data['type'],
                    'movement_reason'  => $data['movement_reason'],
                    'observation'      => $data['observation'] ?? null,
                    'production_order' => $data['production_order'] ?? null,
                    'subtotal'         => $data['subtotal'],
                    'iva'              => $data['iva'],
                    'total'            => $data['total'],
                ]);
            } else {
                $this->revertStockForAdjustment($adjustment, $articles);

                $adjustment->update([
                    'document_number'  => $documentNumber,
                    'date'             => $data['date'],
                    'type'             => $data['type'],
                    'movement_reason'  => $data['movement_reason'],
                    'observation'      => $data['observation'] ?? null,
                    'production_order' => $data['production_order'] ?? null,
                    'subtotal'         => $data['subtotal'],
                    'iva'              => $data['iva'],
                    'total'            => $data['total'],
                ]);

                $adjustment->lines()->delete();
            }

            $this->syncLinesAndStock($adjustment, $data['lines'], $articles, $movementReasons);

            return $adjustment->load('lines.article');
        });
    }

    private function syncLinesAndStock(InventoryAdjustment $adjustment, array $lines, Collection $articles, array $movementReasons): void
    {
        foreach ($lines as $line) {
            $article = $articles[$line['article_id']] ?? null;
            if (!$article) {
                throw ValidationException::withMessages([
                    'lines' => ['No se encontro el articulo indicado.'],
                ]);
            }

            $warehouseId = $this->resolveWarehouseId($article, $line['warehouse_id'] ?? null);
            $lineMovementReason = $line['movement_reason'] ?? $adjustment->movement_reason ?? $adjustment->type;
            $lineType = $movementReasons[$lineMovementReason] ?? $lineMovementReason ?? 'entrada';

            $price = (float) ($line['price'] ?? 0);
            $qty   = (int) ($line['quantity'] ?? 0);
            $tax   = (float) ($line['tax'] ?? 0);
            $base = $price * $qty;
            $taxAmount = $base * $tax / 100;

            InventoryAdjustmentLine::create([
                'inventory_adjustment_id' => $adjustment->id,
                'article_id'              => $line['article_id'],
                'warehouse_id'            => $warehouseId,
                'price'                   => $price,
                'tax'                     => $tax,
                'quantity'                => $qty,
                'description'             => $line['description'] ?? null,
                'line_total'              => $base + $taxAmount,
            ]);

            $this->applyStockMovement($article, $warehouseId, $qty, $lineType);
        }
    }

    private function revertStockForAdjustment(InventoryAdjustment $adjustment, Collection $articles): void
    {
        foreach ($adjustment->lines as $line) {
            $article = $articles[$line->article_id] ?? Article::find($line->article_id);
            if (!$article) {
                continue;
            }

            $warehouseId = $this->resolveWarehouseId($article, $line->warehouse_id);
            $reverseType = $adjustment->type === 'entrada' ? 'salida' : 'entrada';
            $this->applyStockMovement($article, $warehouseId, (int) $line->quantity, $reverseType);
        }
    }
}
