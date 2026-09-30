<?php

namespace App\Services\Production;

use App\Models\Production\FabricRollProgrammingLine;
use App\Models\Production\TechnicalSheet;
use App\Models\Production\TechnicalSheetRoll;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fase 15 del refactor de materiales: las líneas de rollos programados por Diseño
 * viven ahora en fabric_roll_programming_lines (antes: JSON en
 * technical_sheet_rolls.lines). El contrato público {params, lines} se conserva sin
 * cambios para que TechnicalDataSheetRollsController no necesite tocarse — 'lines'
 * sigue siendo un array de arreglos con las mismas claves camelCase de siempre, y
 * cada línea sigue identificándose con un 'id' de tipo string (uuid), ahora guardado
 * en la columna legacy_uuid, no solo para las líneas migradas sino para todas las
 * nuevas también (así el controller sigue generando Str::uuid() para líneas nuevas
 * sin cambiar su contrato de identidad).
 *
 * `params` (trazo/defaultExtra) sigue viviendo en technical_sheet_rolls — solo
 * `lines` se normalizó.
 */
class TechnicalDataSheetRollsStore
{
    private const DEFAULT_TRAZO = 0.8;

    private const DEFAULT_EXTRA = 0.03;

    public function read(int $opId): array
    {
        $sheet = TechnicalSheet::where('production_order_id', $opId)->first();
        if (! $sheet) {
            return $this->defaultPayload();
        }

        $snapshot = $sheet->rollsSnapshot()->first();
        $params = is_array($snapshot?->params) ? $snapshot->params : [];

        $lines = $sheet->rollProgrammingLines()
            ->orderBy('id')
            ->get()
            ->map(fn (FabricRollProgrammingLine $line) => $this->lineToArray($line))
            ->all();

        return [
            'params' => [
                'trazo' => $this->toFloatOrDefault($params['trazo'] ?? null, self::DEFAULT_TRAZO),
                'defaultExtra' => $this->toFloatOrDefault($params['defaultExtra'] ?? null, self::DEFAULT_EXTRA),
            ],
            'lines' => $lines,
        ];
    }

    public function write(int $opId, array $payload): void
    {
        $params = [
            'trazo' => $this->toFloatOrDefault($payload['params']['trazo'] ?? null, self::DEFAULT_TRAZO),
            'defaultExtra' => $this->toFloatOrDefault($payload['params']['defaultExtra'] ?? null, self::DEFAULT_EXTRA),
        ];
        $lines = is_array($payload['lines'] ?? null) ? array_values($payload['lines']) : [];

        $sheet = TechnicalSheet::firstOrCreate(
            ['production_order_id' => $opId],
            ['status' => TechnicalSheet::STATUS_DRAFT],
        );

        TechnicalSheetRoll::updateOrCreate(
            ['technical_sheet_id' => $sheet->id],
            ['params' => $params],
        );

        DB::transaction(function () use ($sheet, $lines) {
            $incomingUuids = [];
            foreach ($lines as $line) {
                if (! is_array($line)) {
                    continue;
                }

                $uuid = trim((string) ($line['id'] ?? ''));
                if ($uuid === '') {
                    $uuid = (string) Str::uuid();
                }
                $incomingUuids[] = $uuid;

                FabricRollProgrammingLine::updateOrCreate(
                    ['technical_sheet_id' => $sheet->id, 'legacy_uuid' => $uuid],
                    $this->arrayToAttributes($line),
                );
            }

            $sheet->rollProgrammingLines()
                ->whereNotIn('legacy_uuid', $incomingUuids)
                ->delete();
        });
    }

    private function lineToArray(FabricRollProgrammingLine $line): array
    {
        return [
            'id' => $line->legacy_uuid,
            'consecutive' => $line->consecutive,
            'type' => $line->type,
            'fabricName' => $line->fabric_name,
            'averageTrace' => $line->average_trace,
            'largoTrazo' => $line->largo_trazo,
            'largoLiq' => $line->largo_liq,
            'factor' => $line->factor,
            'consumptionBase' => $line->consumption_base,
            'pricePerMeter' => $line->price_per_meter,
            'programmedMeters' => $line->programmed_meters,
            'releasedMeters' => (float) $line->released_meters,
            'colors' => $line->color,
            'availableMeters' => $line->available_meters,
            'balanceMeters' => $line->balance_meters,
            'color' => $line->color,
            'colorCode' => $line->color_code,
            'widthCm' => $line->width_cm,
            'supplier' => $line->supplier,
            'extraMeters' => $line->extra_meters,
            'notes' => $line->notes,
            'rawMaterialUid' => $line->raw_material_uid,
            'fabricItemId' => $line->fabric_item_id,
            'rollId' => $line->fabric_roll_id,
            'location' => $line->location,
            'createdAt' => optional($line->created_at)->toISOString(),
            'updatedAt' => optional($line->updated_at)->toISOString(),
        ];
    }

    private function arrayToAttributes(array $line): array
    {
        // released_meters solo lo resuelve FabricRollService::syncSheetReservations() (o el
        // corte): si la línea no lo trae, se conserva el valor guardado.
        $released = array_key_exists('releasedMeters', $line) && is_numeric($line['releasedMeters'])
            ? ['released_meters' => (float) $line['releasedMeters']]
            : [];

        return $released + [
            'consecutive' => $this->nullableText($line['consecutive'] ?? null),
            'type' => ($line['type'] ?? 'PRINCIPAL') === 'SECUNDARIA' ? 'SECUNDARIA' : 'PRINCIPAL',
            'fabric_roll_id' => $this->nullableIntFromMixed($line['rollId'] ?? null),
            'fabric_item_id' => $this->nullableIntFromMixed($line['fabricItemId'] ?? null),
            'raw_material_uid' => $this->nullableText($line['rawMaterialUid'] ?? null),
            'fabric_name' => $this->nullableText($line['fabricName'] ?? null) ?? 'Tela',
            'color' => $this->nullableText($line['color'] ?? $line['colors'] ?? null),
            'color_code' => $this->nullableText($line['colorCode'] ?? null),
            'supplier' => $this->nullableText($line['supplier'] ?? null),
            'location' => $this->nullableText($line['location'] ?? null),
            'notes' => $this->nullableText($line['notes'] ?? null),
            'average_trace' => $this->nullableNumber($line['averageTrace'] ?? null),
            'largo_trazo' => $this->nullableNumber($line['largoTrazo'] ?? null),
            'largo_liq' => $this->nullableNumber($line['largoLiq'] ?? null),
            'factor' => $this->nullableNumber($line['factor'] ?? null),
            'consumption_base' => is_numeric($line['consumptionBase'] ?? null) ? (float) $line['consumptionBase'] : 0.0,
            'price_per_meter' => is_numeric($line['pricePerMeter'] ?? null) ? (float) $line['pricePerMeter'] : 0.0,
            'extra_meters' => is_numeric($line['extraMeters'] ?? null) ? (float) $line['extraMeters'] : 0.0,
            'programmed_meters' => $this->nullableNumber($line['programmedMeters'] ?? null),
            'available_meters' => $this->nullableNumber($line['availableMeters'] ?? null),
            'balance_meters' => $this->nullableNumber($line['balanceMeters'] ?? null),
            'width_cm' => $this->nullableNumber($line['widthCm'] ?? null),
        ];
    }

    private function defaultPayload(): array
    {
        return [
            'params' => [
                'trazo' => self::DEFAULT_TRAZO,
                'defaultExtra' => self::DEFAULT_EXTRA,
            ],
            'lines' => [],
        ];
    }

    private function toFloatOrDefault(mixed $value, float $default): float
    {
        return is_numeric($value) ? (float) $value : $default;
    }

    private function nullableText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $text = trim($value);

        return $text !== '' ? $text : null;
    }

    private function nullableNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function nullableIntFromMixed(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
