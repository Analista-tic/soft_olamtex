<?php

namespace App\Services\MasterData;

use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Color;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Referencia automática de ítems: consecutivo(5) + código de grupo(2) +
 * código de subgrupo(2) + código de color(3). El consecutivo se reserva de forma
 * atómica sobre la misma tabla genérica `finance_document_sequences` que ya usan
 * cotizaciones, pedidos, facturas y notas de venta (mismo patrón que
 * QuoteNumberSequenceService, sin crear una tabla nueva solo para esto).
 *
 * Es exclusiva del alta manual de ítems — el patrón ref-color-talla de productos
 * terminados generados desde curvas/remisión (FinishedProductCatalogItemService)
 * es un flujo totalmente aparte y no pasa por aquí.
 */
class ItemReferenceSequenceService
{
    private const DOCUMENT_TYPE = 'item_auto_reference';

    public function generate(?int $subGroupId, ?int $colorId): string
    {
        $subGroup = $subGroupId ? CategorySubGroup::with('categoryGroup')->find($subGroupId) : null;
        $color = $colorId ? Color::find($colorId) : null;

        $missing = $this->collectMissingCodes($subGroup, $color);
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'reference_mode' => ['No se puede generar la referencia automática: falta '.implode(', ', $missing).'. Complétalo o usa referencia manual.'],
            ]);
        }

        $consecutive = $this->reserveNextNumber();

        return str_pad((string) $consecutive, 5, '0', STR_PAD_LEFT)
            .str_pad((string) $subGroup->categoryGroup->code, 2, '0', STR_PAD_LEFT)
            .str_pad((string) $subGroup->code, 2, '0', STR_PAD_LEFT)
            .str_pad((string) $color->code, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<int, string>
     */
    private function collectMissingCodes(?CategorySubGroup $subGroup, ?Color $color): array
    {
        $missing = [];

        if (! $subGroup) {
            $missing[] = 'el subgrupo';
        } else {
            if (! $subGroup->categoryGroup?->code) {
                $missing[] = 'el código del grupo';
            }
            if (! $subGroup->code) {
                $missing[] = 'el código del subgrupo';
            }
        }

        if (! $color) {
            $missing[] = 'el color';
        } elseif (! $color->code) {
            $missing[] = 'el código del color';
        }

        return $missing;
    }

    private function reserveNextNumber(): int
    {
        return DB::transaction(function () {
            $sequence = DB::table('finance_document_sequences')
                ->where('document_type', self::DOCUMENT_TYPE)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                throw new RuntimeException('No existe la secuencia para referencias automáticas de ítems.');
            }

            $nextValue = (int) $sequence->current_value + 1;

            DB::table('finance_document_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'current_value' => $nextValue,
                    'updated_at' => now(),
                ]);

            return $nextValue;
        });
    }
}
