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
        $text = self::normalizeDisplayText($value);

        if ($text === '') {
            return '';
        }

        return mb_strtoupper($text, 'UTF-8');
    }
}
