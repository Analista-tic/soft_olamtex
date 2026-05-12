<?php

namespace App\Helpers;

/**
 * Parseo canónico de strings de temporada/despacho a mes y año numérico.
 *
 * Soporta formatos:
 *   "ENERO"         → { month: 1,  year: null }
 *   "ENERO 2026"    → { month: 1,  year: 2026 }
 *   "enero 2026"    → { month: 1,  year: 2026 }  (case-insensitive)
 *   "1"             → { month: 1,  year: null }   (legacy numérico)
 *
 * Resultado nulo en ambos si el string no es reconocible.
 */
class SeasonParser
{
    private const MONTHS = [
        'ENERO'      => 1,
        'FEBRERO'    => 2,
        'MARZO'      => 3,
        'ABRIL'      => 4,
        'MAYO'       => 5,
        'JUNIO'      => 6,
        'JULIO'      => 7,
        'AGOSTO'     => 8,
        'SEPTIEMBRE' => 9,
        'OCTUBRE'    => 10,
        'NOVIEMBRE'  => 11,
        'DICIEMBRE'  => 12,
    ];

    /**
     * Parsea un string de temporada o despacho a mes y año.
     *
     * @return array{ month: int|null, year: int|null, ambiguous: bool }
     */
    public static function parse(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return ['month' => null, 'year' => null, 'ambiguous' => false];
        }

        $value = strtoupper(trim($value));
        $parts = preg_split('/\s+/', $value, 2);
        $monthPart = $parts[0] ?? '';
        $yearPart  = $parts[1] ?? null;

        // Resolución del mes
        $month = self::MONTHS[$monthPart] ?? null;

        // Fallback: podría venir como número (legacy)
        if ($month === null && ctype_digit($monthPart)) {
            $candidate = (int) $monthPart;
            if ($candidate >= 1 && $candidate <= 12) {
                $month = $candidate;
            }
        }

        if ($month === null) {
            return ['month' => null, 'year' => null, 'ambiguous' => true];
        }

        // Resolución del año
        $year = null;
        if ($yearPart !== null && ctype_digit($yearPart)) {
            $candidate = (int) $yearPart;
            // Año razonable para el dominio textil
            if ($candidate >= 2020 && $candidate <= 2099) {
                $year = $candidate;
            }
        }

        return ['month' => $month, 'year' => $year, 'ambiguous' => false];
    }

    /**
     * Devuelve el nombre del mes en español en mayúsculas.
     * Retorna null si el número no está en rango.
     */
    public static function monthName(int $month): ?string
    {
        $flipped = array_flip(self::MONTHS);
        return $flipped[$month] ?? null;
    }

    /**
     * Genera el string legacy canónico a partir de mes y año numérico.
     * Formato: "ENERO 2026"
     */
    public static function toCanonicalString(int $month, int $year): string
    {
        $name = self::monthName($month) ?? 'MES';
        return "{$name} {$year}";
    }

    /**
     * Infiere el año desde una fecha Carbon-compatible (Carbon|string|null).
     * Retorna null si no puede determinarse.
     */
    public static function yearFromDate(mixed $date): ?int
    {
        if ($date === null) {
            return null;
        }

        if ($date instanceof \Carbon\Carbon) {
            return (int) $date->year;
        }

        $ts = strtotime((string) $date);
        if ($ts === false) {
            return null;
        }

        return (int) date('Y', $ts);
    }
}
