<?php

namespace App\Services\Production;

use App\Models\Production\ProductSheet;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductSheetStatusService
{
    private const STATUS_COMPLETE = 'complete';
    private const STATUS_INCOMPLETE = 'incomplete';

    /**
     * Promueve a completas solo las fichas incompletas que ya tienen operaciones.
     */
    public function promoteIncompleteWithOperations(): int
    {
        $query = ProductSheet::query()
            ->where('status', self::STATUS_INCOMPLETE)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('product_sheet_operations')
                    ->whereColumn('product_sheet_operations.product_sheet_id', 'product_sheets.id')
                    ->whereNull('product_sheet_operations.deleted_at');
            });

        $ids = (clone $query)->pluck('id')->all();

        $updated = $query->update(['status' => self::STATUS_COMPLETE]);

        // El update masivo no dispara el AuditObserver: se registra un resumen.
        app(AuditLogService::class)->logBulk(
            ProductSheet::class,
            'updated',
            $ids,
            ['status' => self::STATUS_COMPLETE],
            'Promoción automática de fichas incompletas con operaciones',
        );

        return $updated;
    }

    /**
     * Marca el listado operacional como completo (quién y cuándo). Solo se puede si el listado tiene operaciones.
     */
    public function markOperationalListCompleted(ProductSheet $sheet, int $userId): ProductSheet
    {
        $hasOperations = DB::table('product_sheet_operations')
            ->where('product_sheet_id', $sheet->id)
            ->whereNull('deleted_at')
            ->exists();

        if (! $hasOperations) {
            throw ValidationException::withMessages([
                'operational_list' => ['El listado operacional no tiene operaciones: agrega al menos una antes de marcarlo como completo.'],
            ]);
        }

        $sheet->update([
            'operational_list_completed_at' => now(),
            'operational_list_completed_by_user_id' => $userId,
        ]);

        return $sheet->fresh();
    }

    /** Devuelve el listado a "en edición" (sin marca de completo). */
    public function reopenOperationalList(ProductSheet $sheet): ProductSheet
    {
        $sheet->update([
            'operational_list_completed_at' => null,
            'operational_list_completed_by_user_id' => null,
        ]);

        return $sheet->fresh();
    }

    /**
     * Sincroniza estado de una ficha según existencia de operaciones activas.
     * Cualquier cambio en las líneas del listado le quita la marca de completo: el "completo" vale para el contenido que se revisó.
     */
    public function syncForSheet(int $productSheetId): bool
    {
        $completedSheet = ProductSheet::query()
            ->whereKey($productSheetId)
            ->whereNotNull('operational_list_completed_at')
            ->first();
        $completedSheet?->update([
            'operational_list_completed_at' => null,
            'operational_list_completed_by_user_id' => null,
        ]);

        $hasOperations = DB::table('product_sheet_operations')
            ->where('product_sheet_id', $productSheetId)
            ->whereNull('deleted_at')
            ->exists();

        $targetStatus = $hasOperations ? self::STATUS_COMPLETE : self::STATUS_INCOMPLETE;

        // Por instancia para que el AuditObserver registre el cambio de estado.
        $sheet = ProductSheet::query()
            ->whereKey($productSheetId)
            ->where('status', '!=', $targetStatus)
            ->first();

        if (! $sheet) {
            return false;
        }

        $sheet->update(['status' => $targetStatus]);

        return true;
    }

    /**
     * Sincroniza múltiples fichas evitando ids duplicados.
     *
     * @param array<int, int> $productSheetIds
     */
    public function syncForSheets(array $productSheetIds): int
    {
        $updated = 0;

        foreach (array_unique(array_map('intval', $productSheetIds)) as $sheetId) {
            if ($sheetId > 0 && $this->syncForSheet($sheetId)) {
                $updated++;
            }
        }

        return $updated;
    }
}
