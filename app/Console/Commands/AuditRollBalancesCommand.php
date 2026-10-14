<?php

namespace App\Console\Commands;

use App\Models\Textil\FabricRoll;
use App\Models\Textil\FabricRollAdjustment;
use App\Services\Textil\FabricRollService;
use App\Support\Textil\FabricRollBalance;
use Illuminate\Console\Command;

/**
 * Auditoría de saldos por rollo (complementa inventory:reconcile-fabric-rolls, que compara
 * el kardex por ítem contra la suma de rollos).
 *
 * Detecta:
 *  1. Rollos con consumo mayor al metraje (disponibilidad real negativa).
 *  2. Reserva (partial_output) distinta de lo comprometido por las programaciones vigentes.
 *  3. Reserva mayor que la disponibilidad real.
 *  4. Descargues manuales corregidos más de una vez ANTES de la corrección del bug F1: cada
 *     corrección se aplicaba contra la cantidad original, así que el consumo del rollo, de la
 *     línea y el kardex quedaron desviados por Σ(nueva_i − original) de las correcciones previas
 *     a la última.
 *
 * Por defecto solo reporta. Con --apply corrige 1, 2 y 4 con ajustes (y movimientos de kardex)
 * trazables: el consumo mayor al metraje se registra como sobrante de metraje (regla del descargue:
 * la tela puede traer más de lo registrado), pendiente de revisión; 3 se reporta para revisión manual.
 */
class AuditRollBalancesCommand extends Command
{
    protected $signature = 'textil:audit-roll-balances
        {--apply : Corrige consumos mayores al metraje, reservas descuadradas y correcciones aplicadas dos veces, con ajustes trazables}
        {--roll= : Número de rollo específico}';

    protected $description = 'Audita los saldos de cada rollo de tela (consumo, reserva, correcciones duplicadas) y opcionalmente los corrige de forma trazable';

    public function handle(FabricRollService $service): int
    {
        $apply = (bool) $this->option('apply');
        $rollNumber = $this->option('roll');

        $this->info($apply ? 'Aplicando correcciones trazables...' : 'Modo reporte: no se modifica nada (use --apply para corregir).');

        $query = FabricRoll::query()->orderBy('id');
        if ($rollNumber) {
            $query->where('roll_number', $rollNumber);
        }

        $rolls = $query->get();
        $committed = FabricRollBalance::committedByRoll($rollNumber ? $rolls->pluck('id')->all() : null);

        $negative = [];
        $partialMismatch = [];
        $overReserved = [];

        foreach ($rolls as $roll) {
            $real = FabricRollBalance::real($roll);
            $partial = FabricRollBalance::meters((float) $roll->partial_output);
            $expected = FabricRollBalance::meters($committed[$roll->id] ?? 0.0);

            if ($real < -FabricRollBalance::EPSILON) {
                $negative[] = [$roll->roll_number, FabricRollBalance::length($roll), (float) $roll->actual_output, $real];
            }
            if (abs($partial - $expected) > FabricRollBalance::EPSILON) {
                $partialMismatch[] = [$roll->roll_number, $partial, $expected, FabricRollBalance::meters($partial - $expected), $roll->id];
            }
            if ($partial > max(0.0, $real) + FabricRollBalance::EPSILON) {
                $overReserved[] = [$roll->roll_number, $partial, $real];
            }
        }

        $this->section('1. Consumo mayor al metraje (real negativo)', ['Rollo', 'Metraje vigente', 'Consumido', 'Real'], $negative);
        $this->section('2. Reserva distinta de lo comprometido por programaciones', ['Rollo', 'partial_output', 'Comprometido', 'Diferencia'], array_map(fn ($r) => array_slice($r, 0, 4), $partialMismatch));
        $this->section('3. Reserva mayor que la disponibilidad real', ['Rollo', 'Reservado', 'Real'], $overReserved);

        $duplicated = $this->duplicatedCorrections($rollNumber);
        $this->section(
            '4. Descargues corregidos varias veces con el bug anterior (F1)',
            ['Descargue #', 'Rollo', 'Original', 'Vigente', 'Correcciones', 'Desvío aplicado'],
            array_map(fn ($d) => [$d['adjustment']->id, $d['roll_number'], $d['original'], $d['current'], $d['count'], $d['excess']], $duplicated)
        );

        if (! $apply) {
            return self::SUCCESS;
        }

        foreach ($negative as [$number, , , $real]) {
            $roll = FabricRoll::where('roll_number', $number)->firstOrFail();
            $difference = $service->regularizeOverConsumption($roll, 'Auditoría de saldos: el consumo superó el metraje registrado; se registra el excedente como sobrante de metraje');
            if ($difference) {
                $this->line("Rollo {$number}: real {$real} m -> sobrante de {$difference->meters} m registrado (pendiente de revisión)");
            }
        }

        foreach ($partialMismatch as [$number, $partial, $expected, $difference, $rollId]) {
            $service->recalibrateReservation(FabricRoll::findOrFail($rollId), $expected, 'Auditoría de saldos: reserva recalculada desde las programaciones vigentes');
            $this->line("Reserva de {$number}: {$partial} m -> {$expected} m");
        }

        foreach ($duplicated as $item) {
            $service->repairDuplicatedCorrection($item['adjustment'], $item['excess']);
            $this->line("Descargue #{$item['adjustment']->id} ({$item['roll_number']}): se revierte un desvío de {$item['excess']} m");
        }

        $this->info('Correcciones aplicadas. Ejecute inventory:reconcile-fabric-rolls --dry-run para validar el kardex por ítem.');

        return self::SUCCESS;
    }

    /**
     * @return list<array{adjustment: FabricRollAdjustment, roll_number: string, original: float, current: float, count: int, excess: float}>
     */
    private function duplicatedCorrections(?string $rollNumber): array
    {
        $query = FabricRollAdjustment::query()
            ->where('type', 'salida')
            ->where('reference', 'like', 'manual_programming::%')
            ->whereHas('corrections', null, '>=', 2)
            ->with(['corrections', 'roll:id,roll_number']);

        if ($rollNumber) {
            $query->whereHas('roll', fn ($q) => $q->where('roll_number', $rollNumber));
        }

        $result = [];
        foreach ($query->get() as $discharge) {
            $original = FabricRollBalance::meters((float) $discharge->actual_output_after - (float) $discharge->actual_output_before);

            // Correcciones hechas con el código anterior: su "desde" siempre era la cantidad
            // original. Las posteriores al arreglo parten de la cantidad vigente.
            $legacy = $discharge->corrections
                ->filter(fn (FabricRollAdjustment $c) => preg_match('/#\d+:\s*([-\d.]+)\s*m\s*->\s*([-\d.]+)\s*m/u', (string) $c->reason, $m) === 1
                    && abs((float) $m[1] - $original) <= FabricRollBalance::EPSILON)
                ->values();

            if ($legacy->count() < 2) {
                continue;
            }

            // Desvío = Σ(nueva_i − original) de todas menos la última corrección legacy.
            $excess = 0.0;
            foreach ($legacy->slice(0, -1) as $correction) {
                preg_match('/->\s*([-\d.]+)\s*m/u', (string) $correction->reason, $m);
                $excess += (float) $m[1] - $original;
            }
            $excess = FabricRollBalance::meters($excess);

            if (abs($excess) <= FabricRollBalance::EPSILON || $this->alreadyRepaired($discharge)) {
                continue;
            }

            $result[] = [
                'adjustment' => $discharge,
                'roll_number' => (string) $discharge->roll?->roll_number,
                'original' => $original,
                'current' => FabricRollBalance::meters((float) $discharge->quantity),
                'count' => $legacy->count(),
                'excess' => $excess,
            ];
        }

        return $result;
    }

    private function alreadyRepaired(FabricRollAdjustment $discharge): bool
    {
        return FabricRollAdjustment::where('corrects_adjustment_id', $discharge->id)
            ->where('reason', 'like', 'Auditoría de saldos:%')
            ->exists();
    }

    /**
     * @param  list<array<int, mixed>>  $rows
     */
    private function section(string $title, array $headers, array $rows): void
    {
        $this->newLine();
        $this->line("<options=bold>{$title}</>: ".count($rows));
        if ($rows !== []) {
            $this->table($headers, $rows);
        }
    }
}
