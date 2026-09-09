<?php

use App\Models\Production\FabricRollProgrammingLine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 15 — migración de limpieza. Verificado en dev que fabric_roll_programming_lines
 * coincide exactamente con technical_sheet_rolls.lines (26 líneas / 1089.3 m programados
 * en ambos, sin divergencia) desde que se migró el dato. `lines` dejó de escribirse desde
 * entonces (respaldo de solo lectura, congelado) y ningún consumidor lo lee ya — todos
 * migrados a fabric_roll_programming_lines vía TechnicalDataSheetRollsStore.
 *
 * Repite la verificación de conteo/suma antes de borrar la columna, por si el estado
 * de la base al momento de ejecutar esta migración (otro entorno, otra fecha) hubiera
 * cambiado desde que se escribió este archivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Consulta la tabla directamente (sin pasar por el modelo Eloquent): el modelo
        // TechnicalSheetRoll ya no castea `lines` a array (Fase 15 lo retiró de $casts al
        // dejar de usarse), así que decodificar aquí a mano evita depender de ese cast.
        $rows = DB::table('technical_sheet_rolls')->whereNotNull('lines')->get(['technical_sheet_id', 'lines']);

        $jsonCount = 0;
        $jsonSum = 0.0;
        foreach ($rows as $row) {
            $decoded = json_decode((string) $row->lines, true);
            $lines = is_array($decoded) ? $decoded : [];
            foreach ($lines as $line) {
                if (! is_array($line)) {
                    continue;
                }
                $jsonCount++;
                $jsonSum += is_numeric($line['programmedMeters'] ?? null) ? (float) $line['programmedMeters'] : 0.0;
            }
        }

        $newCount = FabricRollProgrammingLine::count();
        $newSum = (float) FabricRollProgrammingLine::sum('programmed_meters');

        if ($newCount !== $jsonCount || abs($newSum - $jsonSum) > 0.01) {
            throw new RuntimeException(
                'No se puede borrar technical_sheet_rolls.lines: la verificación no coincide. '.
                "JSON: {$jsonCount} líneas / {$jsonSum} m programados. ".
                "Tabla nueva: {$newCount} líneas / {$newSum} m programados."
            );
        }

        Schema::table('technical_sheet_rolls', function (Blueprint $table) {
            $table->dropColumn('lines');
        });
    }

    public function down(): void
    {
        Schema::table('technical_sheet_rolls', function (Blueprint $table) {
            $table->json('lines')->nullable()->comment('Líneas de rollos (histórico, restaurado tras rollback)');
        });
    }
};
