<?php

namespace App\Services\Production;

use App\Models\Production\TechnicalSheetSupply;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Persiste insumos de ficha técnica en la tabla relacional
 * `technical_sheet_supplies` (un registro por insumo).
 *
 * El contrato público (read/write/upsertOne/deleteOne) mantiene
 * compatibilidad con el controlador existente.
 */
class TechnicalDataSheetSuppliesStore
{
    /**
     * Lee todos los insumos activos de una OP y los retorna
     * en el formato array camelCase que espera el frontend.
     *
     * @return array<int, array<string, mixed>>
     */
    public function read(int $opId): array
    {
        return TechnicalSheetSupply::query()
            ->where('production_order_id', $opId)
            ->orderBy('sequence')
            ->orderBy('id')
            ->get()
            ->map(fn (TechnicalSheetSupply $s) => $s->toFrontendArray())
            ->values()
            ->all();
    }

    /**
     * Reemplaza la lista completa de insumos de una OP (upsert bulk).
     * Los insumos no presentes en $items son eliminados (soft-delete).
     *
     * @param  array<int, array<string, mixed>>  $items  payload camelCase del frontend
     */
    public function write(int $opId, array $items): void
    {
        DB::transaction(function () use ($opId, $items) {
            $incomingUids = collect($items)
                ->map(fn (array $i) => trim((string) ($i['id'] ?? '')))
                ->filter(fn (string $uid) => $uid !== '')
                ->all();

            // Eliminar (soft-delete) los que ya no están en la lista
            TechnicalSheetSupply::query()
                ->where('production_order_id', $opId)
                ->when(
                    count($incomingUids) > 0,
                    fn ($q) => $q->whereNotIn('uid', $incomingUids)
                )
                ->delete();

            foreach ($items as $index => $item) {
                $this->persistOne($opId, $item, $index);
            }
        });
    }

    /**
     * Crea o actualiza un único insumo (upsert por uid).
     *
     * @param  array<string, mixed>  $item  payload camelCase
     */
    public function upsertOne(int $opId, array $item): TechnicalSheetSupply
    {
        $uid = trim((string) ($item['id'] ?? ''));
        if ($uid === '') {
            $uid = (string) Str::uuid();
        }
        $item['id'] = $uid;

        return DB::transaction(fn () => $this->persistOne($opId, $item));
    }

    /**
     * Elimina (soft-delete) un insumo por su uid.
     */
    public function deleteOne(int $opId, string $uid): void
    {
        TechnicalSheetSupply::query()
            ->where('production_order_id', $opId)
            ->where('uid', trim($uid))
            ->delete();
    }

    // ── Accesos adicionales usados por el controlador ────────────────

    /**
     * Retorna datos de la última compra de un ítem de inventario.
     * Delegado al repositorio de compras; implementación pendiente
     * según módulo de Gastos/Compras del proyecto.
     *
     * @return array<string, mixed>|null
     */
    public function getLastPurchaseForItem(int $itemId): ?array
    {
        // TODO: integrar con PurchaseDocument / PurchaseOrderDetail
        return null;
    }

    /**
     * Meta del ítem de inventario (unidad, descripción, etc.).
     *
     * @return array<string, mixed>|null
     */
    public function getItemMeta(int $itemId): ?array
    {
        $item = \App\Models\MasterData\Item::query()->find($itemId);
        if (!$item) {
            return null;
        }

        return [
            'id'           => (int) $item->id,
            'name'         => trim((string) $item->name),
            'reference'    => $item->reference ?? null,
            'unit_measure' => $item->unit_measure ?? null,
            'description'  => $item->description ?? null,
        ];
    }

    // ── Privado ──────────────────────────────────────────────────────

    /**
     * Persiste (crea o actualiza) un insumo individual.
     *
     * @param  array<string, mixed>  $item
     */
    private function persistOne(int $opId, array $item, int $index = 0): TechnicalSheetSupply
    {
        $uid = trim((string) ($item['id'] ?? '')) ?: (string) Str::uuid();

        /** @var TechnicalSheetSupply $record */
        $record = TechnicalSheetSupply::query()->firstOrNew([
            'production_order_id' => $opId,
            'uid'                 => $uid,
        ]);

        $record->fill([
            'name'              => trim((string) $item['name']),
            'stage'             => $item['stage'] ?? 'GENERAL',
            'sequence'          => isset($item['sequence']) ? (int) $item['sequence'] : ($index + 1),
            'display_label'     => $this->nullableText($item['displayLabel'] ?? null),
            'item_id'           => isset($item['itemId']) && $item['itemId'] !== null ? (int) $item['itemId'] : null,
            'requested_name'    => $this->nullableText($item['requestedName'] ?? null),
            'supplier'          => $this->nullableText($item['supplier'] ?? null),
            'description'       => $this->nullableText($item['description'] ?? null),
            'moda_linea'        => $item['modaLinea'] ?? 'NA',
            'uom'               => $this->nullableText($item['uom'] ?? null),
            'qty_per_unit'      => (float) ($item['qtyPerUnit'] ?? 0),
            'unit_cost'         => (float) ($item['unitCost'] ?? 0),
            'internal_request_id' => isset($item['internalRequestId']) ? (int) $item['internalRequestId'] : null,
            'purchase_request_id' => isset($item['purchaseRequestId']) ? (int) $item['purchaseRequestId'] : null,
            'document_url'      => $this->nullableText($item['documentUrl'] ?? null),
            'notes'             => $this->nullableText($item['notes'] ?? null),
            'is_draft'          => !empty($item['isDraft']),
            'size_measurements' => $this->normalizeSizeMeasurements($item['sizeMeasurements'] ?? null),
        ]);

        $record->save();

        return $record;
    }

    private function nullableText(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $text = trim($value);
        return $text !== '' ? $text : null;
    }

    /**
     * Normaliza el JSON de medidas por talla.
     * Espera: {"S":{"corte":0.66,"termi":0.64}, ...}
     *
     * @return array<string, array{corte: float, termi: float}>|null
     */
    private function normalizeSizeMeasurements(mixed $value): ?array
    {
        if (!is_array($value) || empty($value)) {
            return null;
        }

        $result = [];
        foreach ($value as $size => $measures) {
            if (!is_array($measures)) continue;
            $result[(string) $size] = [
                'corte' => (float) ($measures['corte'] ?? 0),
                'termi' => (float) ($measures['termi'] ?? 0),
            ];
        }

        return empty($result) ? null : $result;
    }
}
