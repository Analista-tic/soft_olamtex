<?php

namespace App\Support\Production;

/**
 * Orden único de tallas: letras de menor a mayor (XXS, XS, S, M, L, XL, XXL...), luego únicas, luego numéricas en
 * orden ascendente y al final cualquier otra. Las tallas se guardan en un JSON de MySQL que reordena las claves,
 * así que el orden nunca puede depender de cómo vengan guardadas. Debe coincidir con resources/js/domain/product-sheet/sizeOrder.ts.
 */
final class SizeOrder
{
    private const LETTER_RANK = [
        'XXS' => 0, 'XS' => 1, 'S' => 2, 'M' => 3, 'L' => 4, 'XL' => 5,
        'XXL' => 6, '2XL' => 6, 'XXXL' => 7, '3XL' => 7, 'XXXXL' => 8, '4XL' => 8,
        'U' => 9, 'UNICA' => 9,
    ];

    /**
     * @param  array<int, string>  $sizes
     * @return array<int, string>
     */
    public static function sort(array $sizes): array
    {
        $sizes = array_values($sizes);
        usort($sizes, static fn (string $a, string $b): int => self::compare($a, $b));

        return $sizes;
    }

    public static function compare(string $a, string $b): int
    {
        [$groupA, $valueA] = self::key($a);
        [$groupB, $valueB] = self::key($b);

        if ($groupA !== $groupB) {
            return $groupA <=> $groupB;
        }

        if ($groupA === 2) {
            return strcmp((string) $valueA, (string) $valueB);
        }

        return $valueA <=> $valueB;
    }

    /** @return array{0: int, 1: int|float|string} */
    private static function key(string $size): array
    {
        $label = mb_strtoupper(trim($size));

        if (isset(self::LETTER_RANK[$label])) {
            return [0, self::LETTER_RANK[$label]];
        }

        if (is_numeric($label)) {
            return [1, (float) $label];
        }

        return [2, $label];
    }
}
