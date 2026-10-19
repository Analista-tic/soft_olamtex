<?php

namespace App\Support\Textil;

use App\Models\Production\FabricRollProgrammingLine;
use App\Models\Production\ManualRollProgramming;
use App\Models\Textil\FabricRoll;
use Illuminate\Support\Facades\DB;

/**
 * Única fuente de las fórmulas de saldo de un rollo de tela.
 *
 * - quantity        cantidad del rollo (recibida, + correcciones explícitas de cantidad). Nunca
 *                   cambia por descargar: es la base de la trazabilidad.
 * - length_adjustment_meters  sobrante (+) / faltante (−) de metraje detectado al descargar,
 *                   neto de reversos. Va aparte para no alterar quantity.
 * - actual_output   metros ya consumidos (descargados).
 * - partial_output  metros reservados por programaciones pendientes (ficha técnica + manual).
 *
 * metraje vigente = quantity + length_adjustment_meters
 * real  = metraje vigente − actual_output                  (lo que físicamente queda en el rollo)
 * libre = metraje vigente − actual_output − partial_output (lo que se puede tomar sin tocar reservas)
 *
 * Listado, Resource, reporte, validaciones y comandos usan estas funciones: antes había
 * cuatro cálculos distintos (uno olvidaba actual_output y otro ignoraba partial_output).
 */
final class FabricRollBalance
{
    /** Tolerancia de comparación en metros (redondeo a 2 decimales de las columnas del rollo). */
    public const EPSILON = 0.001;

    public static function meters(float $value): float
    {
        return round($value, 2);
    }

    /** Metraje vigente del rollo: cantidad + sobrante/faltante de metraje. */
    public static function length(FabricRoll $roll): float
    {
        return self::meters((float) $roll->quantity + (float) $roll->length_adjustment_meters);
    }

    public static function real(FabricRoll $roll): float
    {
        return self::meters(self::length($roll) - (float) $roll->actual_output);
    }

    /**
     * Disponible libre del rollo. $ownReserved devuelve a quien consulta la reserva que
     * ya tiene en el rollo (p. ej. la OP que reprograma su propia ficha técnica).
     */
    public static function free(FabricRoll $roll, float $ownReserved = 0.0): float
    {
        return max(0.0, self::meters(
            self::length($roll) - (float) $roll->actual_output - (float) $roll->partial_output + $ownReserved
        ));
    }

    /**
     * Metros de un consumo que no caben físicamente en el rollo (real = metraje vigente − consumido).
     * Un consumo puede salir de la reserva propia (que no cuenta contra el libre) y aun así dejar el rollo
     * en negativo si la reserva quedó mayor que lo que el rollo tenía: ese exceso es sobrante de metraje,
     * nunca saldo negativo.
     */
    public static function beyondReal(FabricRoll $roll, float $consumed): float
    {
        return max(0.0, self::meters($consumed - max(0.0, self::real($roll))));
    }

    /** Igual que free(), para filas planas (listados con JOIN). */
    public static function freeFromValues(float $quantity, float $actualOutput, float $partialOutput, float $ownReserved = 0.0, float $lengthAdjustment = 0.0): float
    {
        return max(0.0, self::meters($quantity + $lengthAdjustment - $actualOutput - $partialOutput + $ownReserved));
    }

    public static function realFromValues(float $quantity, float $actualOutput, float $lengthAdjustment = 0.0): float
    {
        return self::meters($quantity + $lengthAdjustment - $actualOutput);
    }

    public static function sqlLength(string $alias = 'fabric_rolls'): string
    {
        return "({$alias}.quantity + {$alias}.length_adjustment_meters)";
    }

    public static function sqlReal(string $alias = 'fabric_rolls'): string
    {
        return '('.self::sqlLength($alias)." - {$alias}.actual_output)";
    }

    public static function sqlFree(string $alias = 'fabric_rolls'): string
    {
        return 'GREATEST(0, '.self::sqlLength($alias)." - {$alias}.actual_output - {$alias}.partial_output)";
    }

    /**
     * Reserva comprometida por rollo según las programaciones vigentes — lo que
     * partial_output DEBE valer:
     *   ficha técnica: programado − ya liberado por el corte
     *   manual:        programado − ya consumido − liberado al terminar el rollo
     *
     * @param  array<int, int>|null  $rollIds  null = todos los rollos
     * @return array<int, float>  fabric_roll_id => metros comprometidos
     */
    public static function committedByRoll(?array $rollIds = null): array
    {
        $totals = [];

        $sheetQuery = FabricRollProgrammingLine::query()
            ->whereNotNull('fabric_roll_id')
            ->whereRaw('programmed_meters - released_meters > 0')
            ->selectRaw('fabric_roll_id, SUM(programmed_meters - released_meters) AS total')
            ->groupBy('fabric_roll_id');

        $manualQuery = ManualRollProgramming::query()
            ->whereRaw('programmed_meters - consumed_meters - released_meters > 0')
            ->selectRaw('fabric_roll_id, SUM(programmed_meters - consumed_meters - released_meters) AS total')
            ->groupBy('fabric_roll_id');

        if ($rollIds !== null) {
            if ($rollIds === []) {
                return [];
            }
            $sheetQuery->whereIn('fabric_roll_id', $rollIds);
            $manualQuery->whereIn('fabric_roll_id', $rollIds);
        }

        foreach ([$sheetQuery->get(), $manualQuery->get()] as $rows) {
            foreach ($rows as $row) {
                $rollId = (int) $row->fabric_roll_id;
                $totals[$rollId] = self::meters(($totals[$rollId] ?? 0.0) + (float) $row->total);
            }
        }

        return $totals;
    }

    /** Suma de reservas pendientes de UNA orden de producción en su ficha técnica, por rollo. */
    public static function sheetPendingByRollForOrder(int $productionOrderId): array
    {
        return DB::table('fabric_roll_programming_lines as l')
            ->join('technical_sheets as ts', 'ts.id', '=', 'l.technical_sheet_id')
            ->where('ts.production_order_id', $productionOrderId)
            ->whereNull('ts.deleted_at')
            ->whereNotNull('l.fabric_roll_id')
            ->whereRaw('l.programmed_meters - l.released_meters > 0')
            ->groupBy('l.fabric_roll_id')
            ->selectRaw('l.fabric_roll_id, SUM(l.programmed_meters - l.released_meters) AS total')
            ->pluck('total', 'fabric_roll_id')
            ->map(fn ($total) => self::meters((float) $total))
            ->all();
    }
}
