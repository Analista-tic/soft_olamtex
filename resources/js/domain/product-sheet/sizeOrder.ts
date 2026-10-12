/**
 * Orden único de tallas: letras de menor a mayor (XXS, XS, S, M, L, XL, XXL…), luego únicas, luego numéricas en orden
 * ascendente y al final cualquier otra. Las tallas se guardan en un JSON de MySQL que reordena las claves, así que el
 * orden nunca puede depender de cómo vengan guardadas. Debe coincidir con app/Support/Production/SizeOrder.php.
 */
const LETTER_RANK: Record<string, number> = {
    XXS: 0,
    XS: 1,
    S: 2,
    M: 3,
    L: 4,
    XL: 5,
    XXL: 6,
    '2XL': 6,
    XXXL: 7,
    '3XL': 7,
    XXXXL: 8,
    '4XL': 8,
    U: 9,
    UNICA: 9,
};

function sizeKey(size: string): [number, number | string] {
    const label = String(size ?? '').trim().toUpperCase();

    if (label in LETTER_RANK) return [0, LETTER_RANK[label]];
    if (label !== '' && Number.isFinite(Number(label))) return [1, Number(label)];

    return [2, label];
}

export function compareSizes(a: string, b: string): number {
    const [groupA, valueA] = sizeKey(a);
    const [groupB, valueB] = sizeKey(b);

    if (groupA !== groupB) return groupA - groupB;
    if (typeof valueA === 'number' && typeof valueB === 'number') return valueA - valueB;

    return String(valueA).localeCompare(String(valueB), 'es');
}

export function sortSizes(sizes: string[]): string[] {
    return [...sizes].sort(compareSizes);
}

/** Mismas tallas en el orden estándar (útil para recorrer `Object.entries` sin depender del orden guardado). */
export function sortSizeEntries<T>(entries: [string, T][]): [string, T][] {
    return [...entries].sort(([a], [b]) => compareSizes(a, b));
}
