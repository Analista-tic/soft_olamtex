<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Article;
use App\Models\Inventory\InventoryAdjustment;
use App\Models\Inventory\InventoryAdjustmentLine;
use App\Models\Common\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        if ($request->has('lines') && is_string($request->input('lines'))) {
            $decoded = json_decode($request->input('lines'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $request->merge(['lines' => $decoded]);
            }
        }

        try {
            $data = $request->validate([
                'document_number'      => 'nullable|string|max:255',
                'date'                 => 'required|date',
                'type'                 => 'required|in:entrada,salida',
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
                'lines.*.description'  => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Errores de validacion',
                'errors'  => $e->errors(),
            ], 422);
        }

        $subtotal = 0;
        $ivaTotal = 0;
        foreach ($data['lines'] as $line) {
            $price = (float) ($line['price'] ?? 0);
            $qty   = (int) ($line['quantity'] ?? 0);
            $tax   = (float) ($line['tax'] ?? 0);

            $base = $price * $qty;
            $taxAmount = $base * $tax / 100;

            $subtotal += $base;
            $ivaTotal += $taxAmount;
        }

        $data['document_number'] = $data['document_number'] ?: null;
        $data['subtotal'] = $subtotal;
        $data['iva']      = $ivaTotal;
        $data['total']    = $subtotal + $ivaTotal;

        try {
            $articles = Article::with('warehouses')
                ->whereIn('id', collect($data['lines'])->pluck('article_id'))
                ->get()
                ->keyBy('id');

            $adjustment = DB::transaction(function () use ($data, $articles) {
                $adjustment = InventoryAdjustment::create([
                    'document_number'  => $data['document_number'],
                    'date'             => $data['date'],
                    'type'             => $data['type'],
                    'observation'      => $data['observation'] ?? null,
                    'production_order' => $data['production_order'] ?? null,
                    'subtotal'         => $data['subtotal'],
                    'iva'              => $data['iva'],
                    'total'            => $data['total'],
                ]);

                foreach ($data['lines'] as $line) {
                    $price = (float) ($line['price'] ?? 0);
                    $qty   = (int) ($line['quantity'] ?? 0);
                    $tax   = (float) ($line['tax'] ?? 0);
                    $article = $articles[$line['article_id']] ?? null;

                    if (!$article) {
                        throw ValidationException::withMessages([
                            'lines' => ['No se encontro el articulo indicado.'],
                        ]);
                    }

                    $warehouseId = $this->resolveWarehouseId($article, $line['warehouse_id'] ?? null);

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

                    $this->applyStockMovement($article, $warehouseId, $qty, $data['type']);
                }

                return $adjustment->load('lines.article');
            });

            return response()->json([
                'message' => 'Ajuste de inventario creado correctamente',
                'data'    => $adjustment,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Errores de validacion',
                'errors'  => $e->errors(),
            ], 422);
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

        if ($request->has('lines') && is_string($request->input('lines'))) {
            $decoded = json_decode($request->input('lines'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $request->merge(['lines' => $decoded]);
            }
        }

        try {
            $data = $request->validate([
                'document_number'      => 'nullable|string|max:255',
                'date'                 => 'required|date',
                'type'                 => 'required|in:entrada,salida',
                'observation'          => 'nullable|string',
                'production_order'     => 'nullable|string|max:255',
                'lines'                => 'required|array|min:1',
                'lines.*.article_id'   => 'required|integer|exists:articles,id',
                'lines.*.warehouse_id' => 'nullable|integer|exists:warehouses,id',
                'lines.*.price'        => 'required|numeric|min:0',
                'lines.*.tax'          => 'nullable|numeric|min:0',
                'lines.*.quantity'     => 'required|integer|min:1',
                'lines.*.description'  => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Errores de validacion',
                'errors'  => $e->errors(),
            ], 422);
        }

        $subtotal = 0;
        $ivaTotal = 0;
        foreach ($data['lines'] as $line) {
            $price = (float) ($line['price'] ?? 0);
            $qty   = (int) ($line['quantity'] ?? 0);
            $tax   = (float) ($line['tax'] ?? 0);

            $base = $price * $qty;
            $taxAmount = $base * $tax / 100;

            $subtotal += $base;
            $ivaTotal += $taxAmount;
        }

        $data['document_number'] = $data['document_number'] ?: null;
        $data['subtotal'] = $subtotal;
        $data['iva']      = $ivaTotal;
        $data['total']    = $subtotal + $ivaTotal;

        try {
            $articles = Article::with('warehouses')
                ->whereIn('id', collect($data['lines'])->pluck('article_id'))
                ->get()
                ->keyBy('id');

            $adjustment = DB::transaction(function () use ($adjustment, $data, $articles) {
                // Revertir lineas previas
                foreach ($adjustment->lines as $oldLine) {
                    $article = $articles[$oldLine->article_id] ?? Article::find($oldLine->article_id);
                    if ($article) {
                        $warehouseId = $this->resolveWarehouseId($article, $oldLine->warehouse_id);
                        $reverseType = $adjustment->type === 'entrada' ? 'salida' : 'entrada';
                        $this->applyStockMovement($article, $warehouseId, (int) $oldLine->quantity, $reverseType);
                    }
                }

                $adjustment->update([
                    'document_number'  => $data['document_number'],
                    'date'             => $data['date'],
                    'type'             => $data['type'],
                    'observation'      => $data['observation'] ?? null,
                    'production_order' => $data['production_order'] ?? null,
                    'subtotal'         => $data['subtotal'],
                    'iva'              => $data['iva'],
                    'total'            => $data['total'],
                ]);

                $adjustment->lines()->delete();

                foreach ($data['lines'] as $line) {
                    $price = (float) ($line['price'] ?? 0);
                    $qty   = (int) ($line['quantity'] ?? 0);
                    $tax   = (float) ($line['tax'] ?? 0);
                    $article = $articles[$line['article_id']] ?? null;

                    if (!$article) {
                        throw ValidationException::withMessages([
                            'lines' => ['No se encontro el articulo indicado.'],
                        ]);
                    }

                    $warehouseId = $this->resolveWarehouseId($article, $line['warehouse_id'] ?? null);

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

                    $this->applyStockMovement($article, $warehouseId, $qty, $data['type']);
                }

                return $adjustment->load('lines.article');
            });

            return response()->json([
                'message' => 'Ajuste de inventario actualizado correctamente',
                'data'    => $adjustment,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Errores de validacion',
                'errors'  => $e->errors(),
            ], 422);
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
            throw ValidationException::withMessages([
                'lines' => ["No hay stock suficiente para el articulo {$article->name} en la bodega seleccionada."],
            ]);
        }

        $payload = [
            'quantity'   => max($newQty, 0),
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
}
