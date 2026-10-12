<?php

namespace App\Services\Production;

use App\Models\Production\ProductSheet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductSheetStatusService
{
    private const STATUS_COMPLETE = 'complete';
    private const STATUS_INCOMPLETE = 'incomplete';

    /**
     * Lo que le falta a una sábana para quedar completa. Es la única regla: la usan el servidor al guardar y
     * la pantalla (que muestra esta lista en el estado "Incompleto"). Vacío = completa.
     *
     * @return array<int, string>
     */
    public function missingForCompletion(ProductSheet $sheet): array
    {
        $missing = [];

        $reference = $sheet->type === 'muestra' ? $sheet->sample_reference : $sheet->product_reference;
        if (trim((string) $reference) === '') {
            $missing[] = $sheet->type === 'muestra' ? 'Referencia de muestra' : 'Referencia de producto';
        }

        if ((float) $sheet->selling_price <= 0) {
            $missing[] = 'Precio de venta';
        }

        if (! $this->hasSelectedSize($sheet->sizes)) {
            $missing[] = 'Tallas';
        }

        if (trim((string) $sheet->flat_sketch_path) === '') {
            $missing[] = 'Foto';
        }

        if ($sheet->operational_list_completed_at === null) {
            $missing[] = 'Listado operacional completo';
        }

        return $missing;
    }

    /** Las tallas se guardan como {talla: bool}; también se acepta una lista de nombres. */
    private function hasSelectedSize(mixed $sizes): bool
    {
        if (! is_array($sizes)) {
            return false;
        }

        foreach ($sizes as $key => $value) {
            if ($value === true || $value === 1 || $value === '1') {
                return true;
            }
            if (is_int($key) && is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Promueve a completas las fichas incompletas que ya cumplen la regla completa (nunca degrada ninguna).
     */
    public function promoteIncompleteWithOperations(): int
    {
        $sheets = ProductSheet::query()
            ->where('status', self::STATUS_INCOMPLETE)
            ->whereNotNull('operational_list_completed_at')
            ->get();

        $promoted = 0;
        foreach ($sheets as $sheet) {
            if ($this->missingForCompletion($sheet) === []) {
                // Por instancia para que el AuditObserver registre el cambio de estado.
                $sheet->update(['status' => self::STATUS_COMPLETE]);
                $promoted++;
            }
        }

        return $promoted;
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
        $this->refreshStatus($sheet);

        return $sheet->fresh();
    }

    /** Devuelve el listado a "en edición" (sin marca de completo). */
    public function reopenOperationalList(ProductSheet $sheet): ProductSheet
    {
        $sheet->update([
            'operational_list_completed_at' => null,
            'operational_list_completed_by_user_id' => null,
        ]);
        $this->refreshStatus($sheet);

        return $sheet->fresh();
    }

    /**
     * Cambió el contenido del listado operacional: se quita la marca de completo (valía para lo que se revisó)
     * y se recalcula el estado.
     */
    public function syncForSheet(int $productSheetId): bool
    {
        $sheet = ProductSheet::query()->find($productSheetId);
        if (! $sheet) {
            return false;
        }

        if ($sheet->operational_list_completed_at !== null) {
            $sheet->update([
                'operational_list_completed_at' => null,
                'operational_list_completed_by_user_id' => null,
            ]);
        }

        return $this->refreshStatus($sheet);
    }

    /**
     * Recalcula el estado (completo/incompleto) con la regla única, sin tocar la marca de listado completo.
     *
     * @return bool true si el estado cambió
     */
    public function refreshStatus(ProductSheet $sheet): bool
    {
        $sheet->refresh();
        $target = $this->missingForCompletion($sheet) === [] ? self::STATUS_COMPLETE : self::STATUS_INCOMPLETE;

        if ($sheet->status === $target) {
            return false;
        }

        $sheet->update(['status' => $target]);

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
