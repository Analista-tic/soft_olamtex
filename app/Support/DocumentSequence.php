<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Asignación atómica de consecutivos.
 *
 * El número se decide al GUARDAR (nunca al abrir el formulario) bajo un bloqueo de fila sobre
 * `document_sequences`, así dos usuarios simultáneos no reciben el mismo número. Llamarlo dentro de la
 * transacción que crea el documento mantiene el bloqueo hasta que el registro queda confirmado y, si esa
 * transacción falla, el número no se consume.
 *
 * El contador nunca baja de lo que ya existe en la tabla del documento (`$currentMax`): se autocorrige si
 * hubo importaciones, seeders o inserciones manuales.
 */
final class DocumentSequence
{
    /**
     * @param  string  $scope       Serie, p. ej. `purchase_documents:purchase_invoice:FAC`.
     * @param  Closure  $currentMax  Devuelve el mayor consecutivo ya usado en esa serie (0 si no hay).
     */
    public static function next(string $scope, Closure $currentMax): int
    {
        return DB::transaction(function () use ($scope, $currentMax) {
            DB::table('document_sequences')->insertOrIgnore([
                'scope' => $scope,
                'last_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Lectura actual (no snapshot): trae el último valor confirmado por otra transacción.
            $last = (int) DB::table('document_sequences')->where('scope', $scope)->lockForUpdate()->value('last_value');
            $next = max($last, (int) $currentMax()) + 1;

            DB::table('document_sequences')->where('scope', $scope)->update(['last_value' => $next, 'updated_at' => now()]);

            return $next;
        });
    }

    /**
     * Mayor consecutivo ya usado en una tabla para el prefijo dado. Lee la tabla directamente, así que cuenta
     * también los registros eliminados (soft delete): un número usado nunca se vuelve a asignar.
     */
    public static function maxInTable(string $table, string $column, string $prefix): int
    {
        return self::maxFromNumbers(
            DB::table($table)->whereNotNull($column)->where($column, 'like', $prefix . '-%')->pluck($column),
            $prefix
        );
    }

    /** Mayor consecutivo numérico de una lista de códigos que cumplan `PREFIJO-<dígitos>` exacto. */
    public static function maxFromNumbers(iterable $numbers, string $prefix): int
    {
        $pattern = '/^' . preg_quote($prefix, '/') . '-(\d+)$/';
        $max = 0;

        foreach ($numbers as $number) {
            if (preg_match($pattern, (string) $number, $m) === 1) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max;
    }
}
