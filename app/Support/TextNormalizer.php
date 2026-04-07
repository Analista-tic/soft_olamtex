<?php

namespace App\Support;

class TextNormalizer
{
    public static function titleCaseWords(mixed $value): string
    {
        $text = preg_replace('/\s+/u', ' ', trim((string) ($value ?? '')));

        if ($text === null || $text === '') {
            return '';
        }

        return mb_convert_case($text, MB_CASE_TITLE, 'UTF-8');
    }
}
