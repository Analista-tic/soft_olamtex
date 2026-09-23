<?php

namespace App\Console\Commands;

use App\Models\MasterData\Color;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill de `code` para colores creados antes de que existiera la columna
 * (migración 2026_05_25_120000_add_code_to_colors_table quedó nullable).
 * Sin code, ItemReferenceSequenceService no puede generar la referencia
 * automática de ítems para esos colores ("falta el código del color").
 *
 * Asigna códigos consecutivos continuando desde el máximo existente, con el
 * mismo formato str_pad(3,'0') que usa ColorService::nextCode(), dentro de
 * una única transacción con lock (sobre colors) para evitar condiciones de
 * carrera si corre en paralelo con altas nuevas.
 *
 * Uso:
 *   php artisan colors:backfill-codes
 */
class BackfillColorCodesCommand extends Command
{
    protected $signature = 'colors:backfill-codes';

    protected $description = 'Asigna código consecutivo a los colores existentes que no tienen (code IS NULL)';

    public function handle(): int
    {
        $updated = [];

        DB::transaction(function () use (&$updated) {
            // lockForUpdate sobre toda la tabla: bloquea tanto las filas con
            // código (para leer el máximo real) como las que vamos a
            // actualizar, evitando que una alta concurrente reserve el mismo
            // número mientras corre el backfill.
            $max = Color::query()->lockForUpdate()->max('code');
            $next = ((int) $max) + 1;

            $pending = Color::whereNull('code')
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($pending as $color) {
                if ($next > 999) {
                    $this->error("Se alcanzó el máximo de códigos (999). Deteniendo en color id={$color->id} ('{$color->name}').");
                    break;
                }

                $code = str_pad((string) $next, 3, '0', STR_PAD_LEFT);
                $color->update(['code' => $code]);
                $updated[] = ['id' => $color->id, 'name' => $color->name, 'code' => $code];
                $next++;
            }
        });

        if ($updated === []) {
            $this->info('No hay colores sin código. Nada que hacer.');

            return self::SUCCESS;
        }

        $first = $updated[0];
        $last = $updated[count($updated) - 1];

        $this->info(
            count($updated)." colores actualizados: id={$first['id']} (code={$first['code']}) "
            ."hasta id={$last['id']} (code={$last['code']})."
        );

        return self::SUCCESS;
    }
}
