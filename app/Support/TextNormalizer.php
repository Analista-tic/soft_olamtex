<?php

namespace App\Support;

use Illuminate\Support\Str;

class TextNormalizer
{
    public static function normalizeDisplayText(mixed $value): string
    {
        return preg_replace('/\s+/u', ' ', trim((string) ($value ?? ''))) ?? '';
    }

    public static function semanticKey(mixed $value): string
    {
        $text = self::normalizeDisplayText($value);

        if ($text === '') {
            return '';
        }

        return mb_strtolower(Str::ascii($text), 'UTF-8');
    }

    public static function titleCaseWords(mixed $value): string
    {
        $text = self::normalizeDisplayText($value);

        if ($text === null || $text === '') {
            return '';
        }

        return mb_convert_case($text, MB_CASE_TITLE, 'UTF-8');
    }

    public static function upperCaseWords(mixed $value): string
    {
        return mb_strtoupper(self::normalizeDisplayText($value), 'UTF-8');
    }

    // ── Reglas de guardado por tipo de dato ────────────────────────────
    // Almacenamiento ≠ presentación: estas reglas dejan el dato semánticamente
    // correcto; el formato visual (mayúsculas, etc.) se aplica solo en la interfaz.
    // Mantener en paridad con resources/js/lib/textNormalizer.ts.

    private const NAME_PARTICLES = ['de', 'del', 'la', 'las', 'los', 'y', 'e', 'da', 'do', 'dos', 'van', 'von'];

    /** Email: sin espacios y en minúsculas. */
    public static function email(mixed $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', '', trim((string) ($value ?? ''))) ?? '', 'UTF-8');
    }

    /** Código (SKU, referencia, NIT alfanumérico): espacios normalizados y en mayúsculas. */
    public static function code(mixed $value): string
    {
        return self::upperCaseWords($value);
    }

    /** Nombre de catálogo controlado (subgrupos, operaciones, máquinas): en mayúsculas. */
    public static function catalogName(mixed $value): string
    {
        return self::upperCaseWords($value);
    }

    /**
     * Nombre propio: conserva tildes y la capitalización que escribió la persona.
     * Solo si viene todo en mayúsculas o todo en minúsculas lo pasa a Title Case
     * (con partículas "de", "del", "la"… en minúscula).
     */
    public static function personName(mixed $value): string
    {
        $text = self::normalizeDisplayText($value);

        if ($text === '') {
            return '';
        }

        $hasUpper = $text !== mb_strtolower($text, 'UTF-8');
        $hasLower = $text !== mb_strtoupper($text, 'UTF-8');

        if ($hasUpper && $hasLower) {
            return $text;
        }

        $words = explode(' ', mb_strtolower($text, 'UTF-8'));

        foreach ($words as $index => $word) {
            if ($index > 0 && in_array($word, self::NAME_PARTICLES, true)) {
                continue;
            }

            $words[$index] = mb_convert_case($word, MB_CASE_TITLE, 'UTF-8');
        }

        return implode(' ', $words);
    }

    /** Texto libre (notas, descripciones, direcciones): intacto, solo recorta extremos. */
    public static function freeText(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }
}
