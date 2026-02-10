<?php

namespace App\Helpers;

/**
 * Helper para conversión de números de mes (1-12) a nombres en español.
 * Centraliza la lógica de etiquetas de meses para uso en backend y API responses.
 */
class MonthLabel
{
    private const MONTHS = [
        1 => 'ENERO',
        2 => 'FEBRERO',
        3 => 'MARZO',
        4 => 'ABRIL',
        5 => 'MAYO',
        6 => 'JUNIO',
        7 => 'JULIO',
        8 => 'AGOSTO',
        9 => 'SEPTIEMBRE',
        10 => 'OCTUBRE',
        11 => 'NOVIEMBRE',
        12 => 'DICIEMBRE',
    ];

    /**
     * Convierte un número de mes (1-12) a su nombre en español.
     *
     * @param int|string|null $number Número del mes (1-12) o string numérico
     * @return string Nombre del mes en español o "—" si no es válido
     *
     * @example
     * MonthLabel::fromNumber(2)      // 'FEBRERO'
     * MonthLabel::fromNumber('3')    // 'MARZO'
     * MonthLabel::fromNumber(null)   // '—'
     * MonthLabel::fromNumber(13)     // '—'
     * MonthLabel::fromNumber('MAYO') // 'MAYO' (ya es texto válido)
     */
    public static function fromNumber(int|string|null $number): string
    {
        if ($number === null || $number === '') {
            return '—';
        }

        // Si ya es un nombre de mes válido, retornarlo
        if (is_string($number) && !is_numeric($number)) {
            $upper = strtoupper(trim($number));
            if (in_array($upper, self::MONTHS, true)) {
                return $upper;
            }
            return $number; // Mantener el valor original si no es un mes conocido
        }

        $monthNumber = is_string($number) ? (int) $number : $number;

        if ($monthNumber < 1 || $monthNumber > 12) {
            return '—';
        }

        return self::MONTHS[$monthNumber] ?? '—';
    }

    /**
     * Retorna todos los meses como array asociativo.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return self::MONTHS;
    }

    /**
     * Retorna los meses como opciones para selects (id => name).
     *
     * @return array<int, array{id: int, name: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn(int $id, string $name) => ['id' => $id, 'name' => $name],
            array_keys(self::MONTHS),
            self::MONTHS
        );
    }
}
