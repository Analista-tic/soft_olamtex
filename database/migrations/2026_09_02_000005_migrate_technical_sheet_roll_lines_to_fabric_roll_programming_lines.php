<?php

use App\Models\MasterData\Item;
use App\Models\Production\FabricRollProgrammingLine;
use App\Models\Textil\FabricRoll;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Fase 15: migra cada línea de technical_sheet_rolls.lines (JSON) a una fila de
 * fabric_roll_programming_lines. No borra `lines` (queda como respaldo de solo
 * lectura hasta una migración de limpieza posterior, tras confirmar en producción).
 *
 * Verifica al final que el conteo de líneas y la suma de programmedMeters coinciden
 * exactamente entre el JSON de origen y la tabla nueva — si no coinciden, revierte
 * la migración de datos (no la deja a medias) y lanza una excepción explicando la
 * diferencia, en vez de asumir que "no truene" es suficiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Consulta la tabla directamente (sin pasar por el modelo Eloquent): un
        // migrate:fresh en un clon nuevo carga el modelo TechnicalSheetRoll tal como
        // está HOY (Fase 15 le retiró el cast 'lines' => 'array' al dejar de usarse),
        // no como estaba cuando se escribió esta migración — depender del cast del
        // modelo aquí insertaría 0 filas en silencio en cualquier instalación nueva.
        $snapshots = DB::table('technical_sheet_rolls')->whereNotNull('lines')->get(['technical_sheet_id', 'lines']);

        $validRollIds = FabricRoll::query()->pluck('id')->flip()->all();
        $validItemIds = Item::query()->withTrashed()->pluck('id')->flip()->all();

        $jsonLineCount = 0;
        $jsonMetersSum = 0.0;
        $orphanRollRefs = 0;
        $orphanItemRefs = 0;

        DB::transaction(function () use (
            $snapshots,
            $validRollIds,
            $validItemIds,
            &$jsonLineCount,
            &$jsonMetersSum,
            &$orphanRollRefs,
            &$orphanItemRefs,
        ) {
            foreach ($snapshots as $snapshot) {
                $decoded = json_decode((string) $snapshot->lines, true);
                $lines = is_array($decoded) ? $decoded : [];

                foreach ($lines as $line) {
                    if (! is_array($line)) {
                        continue;
                    }

                    $jsonLineCount++;
                    $meters = is_numeric($line['programmedMeters'] ?? null) ? (float) $line['programmedMeters'] : 0.0;
                    $jsonMetersSum += $meters;

                    $rollId = $this->nullableIntFromMixed($line['rollId'] ?? null);
                    if ($rollId !== null && ! isset($validRollIds[$rollId])) {
                        $orphanRollRefs++;
                        $rollId = null;
                    }

                    $itemId = $this->nullableIntFromMixed($line['fabricItemId'] ?? null);
                    if ($itemId !== null && ! isset($validItemIds[$itemId])) {
                        $orphanItemRefs++;
                        $itemId = null;
                    }

                    $legacyUuid = trim((string) ($line['id'] ?? ''));
                    if ($legacyUuid === '') {
                        $legacyUuid = (string) Str::uuid();
                    }

                    $createdAt = $this->parseDateOrNow($line['createdAt'] ?? null);
                    $updatedAt = $this->parseDateOrNow($line['updatedAt'] ?? null);

                    FabricRollProgrammingLine::create([
                        'technical_sheet_id' => $snapshot->technical_sheet_id,
                        'legacy_uuid' => $legacyUuid,
                        'consecutive' => $this->nullableText($line['consecutive'] ?? null),
                        'type' => ($line['type'] ?? 'PRINCIPAL') === 'SECUNDARIA' ? 'SECUNDARIA' : 'PRINCIPAL',
                        'fabric_roll_id' => $rollId,
                        'fabric_item_id' => $itemId,
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
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                    ]);
                }
            }

            $newCount = FabricRollProgrammingLine::count();
            $newMetersSum = (float) FabricRollProgrammingLine::sum('programmed_meters');

            if ($newCount !== $jsonLineCount || abs($newMetersSum - $jsonMetersSum) > 0.01) {
                throw new RuntimeException(
                    'Migración de líneas de rollos: verificación falló, se revierte. '.
                    "JSON original: {$jsonLineCount} líneas / {$jsonMetersSum} m programados. ".
                    "Tabla nueva: {$newCount} líneas / {$newMetersSum} m programados."
                );
            }

            Log::info(
                "Migración fabric_roll_programming_lines OK: {$newCount} líneas, {$newMetersSum} m programados ".
                "(coincide con el JSON original). Referencias huérfanas puestas en null: {$orphanRollRefs} rollo(s), {$orphanItemRefs} ítem(s)."
            );
        });
    }

    public function down(): void
    {
        FabricRollProgrammingLine::query()->delete();
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

    private function parseDateOrNow(mixed $value): Carbon
    {
        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value);
            } catch (Throwable) {
                // cae al now() de abajo
            }
        }

        return Carbon::now();
    }
};
